<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/13
 * Time: 16:05
 * 养号任务脚本,每天凌晨初始化今日养号任务
 */
class TaskRun_TrainTask extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='trainTask';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        set_time_limit(0);
        try{
            $model = Model_TrainTasks::getInstance();
            $pagesize = 500;
            $page = 1;
            $flag = true;
            $onlienWxIDs = Model_Device::getInstance()->findOnlineWeixin();
            if(empty($onlienWxIDs)){
                self::getLog()->add('未找到在线微信号设备,终止任务')->flush();
                die();
            }
            //获取聊天库数据
            $chatContent = mb_convert_encoding(@file_get_contents(APPLICATION_PATH.'/data/auto_chat.txt'), 'utf-8', 'gbk');
            self::getLog()->add('----------start train-----------')->flush();
            do {
                try{
                    $data = $model->fromSlaveDB()->select()->where('Status = ?', Model_TrainTasks::STATUS_ON)->where('StartDate <= ?', date('Y-m-d'))->where('EndDate >= ?', date('Y-m-d'))->order('TrainTaskID Asc')->limitPage($page, $pagesize)->query()->fetchAll();
                    if(!$data){
                        $flag = false;
                    }else {
                        foreach ($data as $row){
                            self::getLog()->add('开始处理,TrainTaskID:'.$row['TrainTaskID']);
                            if(empty($row['WeixinTags'])){
                                self::getLog()->add('微信标签为空');
                                continue;
                            }
                            $mates = []; //当前任务素材数据
                            //查询此标签下在线的微信,在线才能发任务
                            $wSelect = Model_Weixin::getInstance()->fromSlaveDB()->select()->from(Model_Weixin::getInstance()->getTableName(), ['WeixinID', 'Weixin'])
                                    ->where('WeixinID IN (?)', $onlienWxIDs);
                            $where_msg ='';
                            $category_data = explode(',', $row['WeixinTags']);
                            foreach($category_data as $w){
                                if ((int)$w>0){
                                    $where_msg .= "FIND_IN_SET(".$w.",CategoryIds) OR ";
                                }
                            }
                            $where_msg = rtrim($where_msg,'OR ');
                            $wSelect->where($where_msg);
                            $weixins = $wSelect->query()->fetchAll();
                            $weixinSend = array();
                            if ($weixins){
                                foreach ($weixins as $v){
                                    $weixinSend[$v['WeixinID']] = $v['Weixin'];
                                }
                            }
                            if(count(($weixinSend)) <= 0){
                                self::getLog()->add('标签下无在线微信号');
                                continue;
                            }
                            foreach ($weixinSend as $weixinID=>$weixin){
                                self::getLog()->add('开始处理具体个号,WeixinID:'.$weixinID.',Weixin:'.$weixin);
                                $ownFriends = []; //此微信号的好友是自己后台的微信个号
                                if($row['ViewMessageEnable']){
                                    //看新闻
                                    try{
                                        $viewMessageTaskID = Model_Task::addCommonTask(TASK_CODE_VIEW_MESSAGE, $weixinID, '', $row['AdminID'], Helper_Until::getRandTime(date('Y-m-d 07:00:00'), date('Y-m-d 21:00:00')));
                                        self::getLog()->add('生成看新闻任务，TaskID:'.$viewMessageTaskID);
                                    }catch(Exception $e){
                                        self::getLog()->add('deal ViewMessageEnable error:'.$e->getMessage());
                                    }
                                }
                                if($row['ViewNewEnable']){
                                    //点未读消息
                                    try{
                                        $viewNewTaskID = Model_Task::addCommonTask(TASK_CODE_VIEW_NEWS, $weixinID, '', $row['AdminID'], Helper_Until::getRandTime(date('Y-m-d 07:00:00'), date('Y-m-d 21:00:00')));
                                        self::getLog()->add('生成看未读消息任务，TaskID:'.$viewNewTaskID);
                                    }catch(Exception $e){
                                        self::getLog()->add('deal ViewNewEnable error:'.$e->getMessage());
                                    }

                                }
                                if($row['AddFriendConfig']){
                                    //添加好友{"Enable":"1","DayNum":"10","TotalNum":"50"}
                                    try{
                                        List($flag, $msg) = Model_TrainTasks::checkConfigData('AddFriendConfig', $row['AddFriendConfig']);
                                        if($flag){
                                            $addFriendConfig = json_decode($row['AddFriendConfig'], true);
                                            if($addFriendConfig['Enable']){
                                                //查询当前微信拥有后台自己的微信号好友
                                                $ownFriends = Model_Weixin_Friend::getInstance()->getAdminFriendWx($weixinID);
                                                if(count($ownFriends) < $addFriendConfig['TotalNum']){
                                                    $addNum = $addFriendConfig['TotalNum'] - count($ownFriends) > $addFriendConfig['DayNum']? $addFriendConfig['DayNum']:$addFriendConfig['TotalNum'] - count($ownFriends);
                                                    $addSelect = Model_Weixin::getInstance()->fromSlaveDB()->select()->from('weixins', ['Alias','PhoneNumber'])->where('WeixinID != ?', $weixinID)->where("Alias !='' or PhoneNumber != ''");
                                                    if(count($ownFriends) > 0){
                                                        $addWeixins = $addSelect->where('Weixin not in (?)', array_values($ownFriends))->order('WeixinID Asc')->limit($addNum)->query()->fetchAll();
                                                    }else{
                                                        $addWeixins = $addSelect->order('WeixinID Asc')->limit($addNum)->query()->fetchAll();
                                                    }
                                                    if(!empty($addWeixins)){
                                                        //发送添加好友任务
                                                        foreach ($addWeixins as $addWeixin){
                                                            $addConfig = [
                                                                'SendWeixins' => [],
                                                                'Weixin' => $weixin,
                                                                'AddNum' => 1,
                                                                'CopyWriting' => 'hello,多麦内部好友'
                                                            ];
                                                            if($addWeixin['Alias']){
                                                                //根据微信添加好友
                                                                $addConfig['SendWeixins'] = [[
                                                                    'Wx' => $addWeixin['Alias'],
                                                                    'Type' => 1,
                                                                    'V1' => null,
                                                                    'V2' => null
                                                                ]];
                                                            }else if($addWeixin['PhoneNumber']){
                                                                //根据手机号添加好友
                                                                $addConfig['SendWeixins'] = [[
                                                                    'Wx' => $addWeixin['PhoneNumber'],
                                                                    'Type' => 3,
                                                                    'V1' => null,
                                                                    'V2' => null
                                                                ]];
                                                            }

                                                            $friendTaskID = Model_Task::addCommonTask(TASK_CODE_WXFRIEND_JOIN, $weixinID, json_encode($addConfig), $row['AdminID'], Helper_Until::getRandTime(date('Y-m-d 07:00:00'), date('Y-m-d 21:00:00')));
                                                            self::getLog()->add('生成加好友任务，TaskID:'.$friendTaskID);
                                                        }
                                                    }else{
                                                        self::getLog()->add('此微信号未找到可添加的好友数据');
                                                    }
                                                }else{
                                                    self::getLog()->add('此微信号已有自己后台个号好友数超过上限,好友数:'.count($ownFriends).',上限:'.$addFriendConfig['TotalNum']);
                                                }
                                            }
                                        }else{
                                            self::getLog()->add($msg);
                                        }
                                    }catch(Exception $e){
                                        self::getLog()->add('deal AddFriendConfig error:'.$e->getMessage());
                                    }
                                }
                                if($row['ChatConfig']){
                                    //好友聊天{"Enable":"1","Time":[{"Start":"10:00","End":"12:00"},{"Start":"16:00","End":"18:00"}]}
                                    try{
                                        List($flag, $msg) = Model_TrainTasks::checkConfigData('ChatConfig', $row['ChatConfig']);
                                        if($flag){
                                            $chatConfig = json_decode($row['ChatConfig'], true);
                                            if($chatConfig['Enable']){
                                                if($chatContent !== ''){
                                                    $chatContentArr = preg_split("#\n#", $chatContent, -1, PREG_SPLIT_NO_EMPTY);
                                                    //找到此微信号后台个号好友
                                                    if(empty($ownFriends)){
                                                        $ownFriends = Model_Weixin_Friend::getInstance()->getAdminFriendWx($weixinID);
                                                    }
                                                    $canChatWxID = []; //可聊天的后台好友WeixinID
                                                    foreach ($ownFriends as $ID => $wx){
                                                        //不需要聊天对方个号在线，先发任务
                                                        //if(in_array($ID, $onlienWxIDs) && $ID != $weixinID){
                                                        if($ID != $weixinID){
                                                            //排除自己
                                                            $canChatWxID[] = $ID;
                                                        }
                                                        //}
                                                    }
                                                    if(empty($canChatWxID)){
                                                        self::getLog()->add('未找到此微信号可聊天的后台个号');
                                                    }else{
                                                        $chatID = $canChatWxID[mt_rand(0, count($canChatWxID)-1)];
                                                        foreach ($chatConfig['Time'] as $chatTime){
                                                            //每个时间段随机10个时间点 发送一次对话
                                                            for ($s = 0; $s < 10; $s++){
                                                                $chatData = [
                                                                    'MessageID' => "",
                                                                    'ChatroomID' => "",
                                                                    'WxAccount' => $ownFriends[$chatID],
                                                                    'content' => trim($chatContentArr[mt_rand(0, count($chatContentArr)-1)]),
                                                                    'type' => 1
                                                                ];
                                                                $replayData = [
                                                                    'MessageID' => "",
                                                                    'ChatroomID' => "",
                                                                    'WxAccount' => $weixin,
                                                                    'content' => trim($chatContentArr[mt_rand(0, count($chatContentArr)-1)]),
                                                                    'type' => 1
                                                                ];
                                                                $runTime = Helper_Until::getRandTime(date('Y-m-d '.$chatTime['Start'].':00'), date('Y-m-d '.$chatTime['End'].':00'));
                                                                try{
                                                                    $jobID1 = Helper_DisQueue::getInstance()->inQueue(Helper_DisQueue::job_name_msgSend, ['ReceiverWxId' => $weixinID, 'Data' => $chatData], strtotime($runTime) - time());
                                                                    $jobID2 = Helper_DisQueue::getInstance()->inQueue(Helper_DisQueue::job_name_msgSend, ['ReceiverWxId' => $chatID, 'Data' => $replayData], strtotime($runTime) - time());
                                                                    self::getLog()->add('生成自动聊天消息队列，JobID:'.$jobID1.','.$jobID2);
                                                                }catch(Exception $e){
                                                                    self::getLog()->add('消息队列 err:'.$e->getMessage());
                                                                }
                                                            }
                                                        }
                                                    }
                                                }else{
                                                    self::getLog()->add('未找到聊天库数据');
                                                }
                                            }
                                        }else{
                                            self::getLog()->add($msg);
                                        }
                                    }catch(Exception $e){
                                        self::getLog()->add('deal ChatConfig error:'.$e->getMessage());
                                    }

                                }
                                if($row['SendAlbumConfig']){
                                    //发朋友圈{"Enable":"1","MateTagIDs":"1,2","Start":"16:00","End":"18:00","DayNum":"5"}
                                    try{
                                        List($flag, $msg) = Model_TrainTasks::checkConfigData('SendAlbumConfig', $row['SendAlbumConfig']);
                                        if($flag) {
                                            $sendAlbumConfig = json_decode($row['SendAlbumConfig'], true);
                                            if ($sendAlbumConfig['Enable']) {
                                                //查到所有素材
                                                if(empty($mates)){
                                                    $mates = Model_Materials::getInstance()->findByTagIDs(explode(',', $sendAlbumConfig['MateTagIDs']), ['MaterialID','Type']);
                                                }
                                                $mateIDs = [];
                                                $mateInfos = []; //素材信息 ID=>TYPE
                                                foreach ($mates as $mate){
                                                    $mateIDs[] = $mate['MaterialID'];
                                                    $mateInfos[$mate['MaterialID']] = $mate['Type'];
                                                }
                                                //获取此个号已经发过朋友圈的素材ID数组
                                                $albumMateIDs = Model_Weixin::getAlbumMateIDs($weixinID);
                                                //筛选出未发过朋友圈的所有素材ID数组
                                                $canAlbumMateIDs = array_diff($mateIDs, $albumMateIDs);
                                                if(!empty($canAlbumMateIDs)){
                                                    $scheduleData = [
                                                        'WxIdType' => 'WX_ID',
                                                        'WeixinIDs' => $weixinID,
                                                        'StartDate' => date('Y-m-d'),
                                                        'EndDate' => date('Y-m-d'),
                                                        'AddTime' => date('Y-m-d H:i:s'),
                                                        'AdminID' => $row['AdminID'],
                                                    ]; //要写入排期表的数据
                                                    $scheConfig = [];
                                                    $mateNormalNum = $mateProductNum = 0; //素材类型数量
                                                    $sendNum = count($canAlbumMateIDs) > $sendAlbumConfig['DayNum']?$sendAlbumConfig['DayNum']:count($canAlbumMateIDs);
                                                    $sendMateIDs = []; //发圈的素材ID数组
                                                    for ($d = 0; $d < $sendNum; $d++){
                                                        shuffle($canAlbumMateIDs);
                                                        $sendMateID = array_pop($canAlbumMateIDs); //随机要发圈的素材ID
                                                        $sendMateIDs[] = $sendMateID;
                                                        $execTime = Helper_Until::getRandTime(date('Y-m-d '.$sendAlbumConfig['Start'].':00'), date('Y-m-d '.$sendAlbumConfig['End'].':00'));
                                                        $scheConfig[]=[
                                                            'ExecType' => 'REFER',
                                                            'ExecTime' => $execTime,
                                                            'MateID' => $sendMateID
                                                        ];
                                                        if(isset($mateInfos[$sendMateID])){
                                                            if($mateInfos[$sendMateID] == 1){
                                                                $mateNormalNum += 1;
                                                            }else{
                                                                $mateProductNum += 1;
                                                            }
                                                        }
                                                    }
                                                    $sortExce = [];
                                                    foreach ($scheConfig as $config){
                                                        $sortExce[] = $config["ExecTime"];
                                                    }
                                                    array_multisort($scheConfig, SORT_ASC, $sortExce);//按发圈时间ASC排序
                                                    $scheduleData['ScheConfigs'] = json_encode(array_values($scheConfig));
                                                    $scheduleData['NextRunTime'] = $scheConfig[0]['ExecTime'];
                                                    $scheduleData['NormalMateNum'] = $mateNormalNum;
                                                    $scheduleData['ProductMateNum'] = $mateProductNum;
                                                    $lastScheduleID = Model_Schedules::getInstance()->fromMasterDB()->insert($scheduleData);
                                                    Model_Weixin::setAlbumMateID($weixinID, $sendMateIDs); //发过的素材ID写入关系
                                                    self::getLog()->add('生成发朋友圈排期任务，ScheduleID:'.$lastScheduleID);
                                                }else{
                                                    self::getLog()->add('无可发送的素材');
                                                }
                                            }
                                        }else{
                                            self::getLog()->add($msg);
                                        }
                                    }catch(Exception $e){
                                        self::getLog()->add('deal SendAlbumConfig error:'.$e->getMessage());
                                    }
                                }
                                if($row['AlbumInteractConfig']){
                                    //朋友圈互动{"Enable":"1","Start":"16:00","End":"18:00","DayNum":"5","LikeNum":"2"}
                                    try{
                                        List($flag, $msg) = Model_TrainTasks::checkConfigData('AlbumInteractConfig', $row['AlbumInteractConfig']);
                                        if($flag) {
                                            $albumInteractConfig = json_decode($row['AlbumInteractConfig'], true);
                                            if ($albumInteractConfig['Enable']) {
                                                for ($l = 0; $l < $albumInteractConfig['DayNum']; $l++){
                                                    $album_config = [
                                                        'LikeNum' => $albumInteractConfig['LikeNum']
                                                    ];
                                                    $albumTaskID = Model_Task::addCommonTask(TASK_CODE_TRAIN_ALBUM_DEAL, $weixinID, json_encode($album_config), $row['AdminID'], Helper_Until::getRandTime(date('Y-m-d '.$albumInteractConfig['Start'].':00'), date('Y-m-d '.$albumInteractConfig['End'].':00')));
                                                    self::getLog()->add('生成朋友圈模拟任务，TaskID:'.$albumTaskID);
                                                }
                                            }
                                        }else{
                                            self::getLog()->add($msg);
                                        }
                                    }catch(Exception $e){
                                        self::getLog()->add('deal AlbumInteractConfig error:'.$e->getMessage());
                                    }
                                }
                            }
                        }
                        $page++;
                    }
                } catch (Exception $e){
                    self::getLog()->add('deal error:'.$e->getMessage());
                }
            }while ($flag);
            self::getLog()->add('----------end train-----------')->flush();
            die();
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage())->flush();
        }
    }

    protected function init()
    {
        parent::init();
        self::getLog()->add("\n\n**********************定时更新**************************");
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: '.$this->getReleaseCheck()->getRelease().', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }

}