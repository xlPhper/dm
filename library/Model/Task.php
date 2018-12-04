<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 14:28
 */

class Model_Task extends DM_Model
{
    public static $table_name = "tasks";
    protected $_name = "tasks";
    protected $_primary = "TaskID";
    
    /**
     * 任务运行时间配置
     */
    public static function taskRunTimeConfig($runType, array $runTimes, $maxRunNums = 0, $expectType = '', $expectTimes = '')
    {
        /**
        `MaxRunNums` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最大执行次数,0表示无限制',
        `TaskRunTime` varchar(2000) NOT NULL DEFAULT '' COMMENT '任务执行时间json',
        `ExpectTime` varchar(2000) NOT NULL DEFAULT '' COMMENT '执行排除执行时间json',
        `NextRunTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '下次执行时间',
         */
        /**
        写入任务时根据当前情况写入下次执行时间, 执行完后记录此次执行时间及下次执行时间
        MaxRunNums:(最大执行次数)
        AlreadyNums:(已执行次数)
        TaskRunTime:(执行时间json)<cron定时执行,refer指定时间>
        {"type":"cron","time":{"5 * * * *","* 23 * * *"}}
        {"type":"refer","time":"2018-08-30 12:00:00,2018-08-30 13:00:00"}
        ExpectTime:(排除时间json)<cron定时执行,refer指定时间>
        {"type":"cron","time":{"* * * * 6","* * * * 0"}}
        {"type":"refer","time":"2018-08-30 12:00:00,2018-08-30 13:00:00"}
        NextRunTime:下一次执行时间
        LastRunTime:最后一次执行时间
         */
//        基本格式 :
//        *　　*　　*　　*　　*　　command
//        分　时　日　月　周　命令
//        第1列表示分钟1～59 每分钟用*或者 */1表示
//        第2列表示小时1～23（0表示0点）
//        第3列表示日期1～31
//        第4列表示月份1～12
//        第5列标识号星期0～6（0表示星期天）
//        第6列要运行的命令
        if (!in_array($runType, ['cron', 'refer'])) {
            return [false, '执行方式非法'];
        }
        $runTimesArr = $runTimes;
        foreach ($runTimesArr as $runTime) {
            if($runType == 'cron' && false == DM_Crontab::format_crontab($runTime)) {
                return [false, '执行时间非法'];
            }
            if ($runType == 'refer' && false == strtotime($runTime)) {
                return [false, '执行时间非法'];
            }
        }
        sort($runTimesArr );
        $taskRunTime = [
            'type' => $runType,
            'time' => $runTimesArr
        ];
        $taskExpectTime = [];
        // 根据 crontab 获取下一次执行时间
        if ($runType == 'cron') {
            $nextRunTime = self::getNextRunTime($taskRunTime, $taskExpectTime);
        } else {
            $nextRunTime = $taskRunTime['time'][0];
        }


        return [true, [
            'MaxRunNums' => $maxRunNums,
            'TaskRunTime' => json_encode($taskRunTime),
            'NextRunTime' => $nextRunTime
        ]];
    }

    /**
     * 获取下一次执行时间
     */
    public static function getNextRunTime($runTime, $lastRunTime = '', $expectTime = [])
    {
        if ($runTime['type'] == 'cron') {
            $nextTaskRunTime = DM_Crontab::getNextCronTime($runTime['time']);
        } else {
            $nextTaskRunTime = $lastRunTime ?? date('Y-m-d H:i:s');
            foreach ($runTime['time'] as $time) {
                if ($time > $nextTaskRunTime) {
                    $nextTaskRunTime = $time;
                    break;
                }
            }
        }
        return $nextTaskRunTime;
    }

    /**
     * 添加任务
     *
     * @param $DeviceID 设备ID
     * @param $WeixinID 微信ID
     * @param string $TaskCode 任务代码
     * @param $TaskRunTime 指定运行时间
     * @param $TaskConfig   任务配置
     * @param $TaskBody 执行主体身份
     * @param null $BodyID  自定义执行主体ID
     * @param null $ParentID    执行主体上级ID
     */
    public function add($TaskCode, $data)
    {
        if(isset($data['TaskRunTime'])){
            if(DM_Crontab::format_crontab($data['TaskRunTime']) == false){
                return false;
            }
        }

        $groupModel = new Model_Group();
        $task_run_time = [
            'type'=>"cron",
            'time'=>$data['TaskRunTime']
        ];
        $insert_data = [
//            'UserID'    =>  Zend_Registry::get('USERID'),
//            'DeviceID'  =>  $data['DeviceID'],
            'WeixinID'  =>  $data['WeixinID'] ?? 0,
            'TaskCode'  =>  $TaskCode,
            'TaskRunTime'   =>  json_encode($task_run_time),
            'TaskConfig'    =>  json_encode($data['TaskConfig']),
            'FinishID'  =>  json_encode([]),
            'SuccessNum'    =>  0,
            'FailureNum'    =>  0,
            'Status'    =>  0
        ];
//        if(!isset($data['TotalID']) || empty($data['TotalID'])){
//            switch ($data['TaskBody']){
//                case TASK_BODY_GROUP:
//                    $insert_data['TotalID'] = $groupModel->getGroupIDByWeixinID($data['WeixinID']);
//                    break;
//                default:
//                    $insert_data['TotalID'] = json_encode([]);
//            }
//        }else{
//            $insert_data['TotalID'] = $data['TotalID'];
//        }
//        $insert_data['TotalNum'] = count($insert_data['TotalID']);
        $insert_data['TotalNum'] = 1;
        $this->insert($insert_data);
        return $this->_db->lastInsertId();
    }

    public function getNewTasksByOnlineWxIds($onlineWxIds, $limit = 1)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where("unix_timestamp() < (unix_timestamp(NextRunTime) + 3600)")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums')
            ->where('WeixinID in (?)', $onlineWxIds)
            ->where("Status = ?", TASK_STATUS_NOTSTART)
            ->where('IsSendClient = ?', 'Y')
            ->order("Level Desc")
            ->order("AddDate Asc")
            ->limit($limit);
        $data = $this->_db->fetchAll($select);

        return $data;
    }

    /**
     * 获取最新符合要求的任务
     * @param $DeviceID
     * @param null $OnlineWeixinID
     * @return bool|mixed
     */
    public function getNewTask($DeviceID, $OnlineWeixinID = null)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where("unix_timestamp() < (unix_timestamp(NextRunTime) + 3600)")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums')
            ->where('WeixinID = ?', $OnlineWeixinID)
            ->where('IdType = ?', 'WXID')
            ->where("Status = ?", TASK_STATUS_NOTSTART)
            ->where('IsSendClient = ?', 'Y')
            ->order("Level Desc")
            ->order("AddDate Asc");
        $data = $this->_db->fetchAll($select);
        if($data) {
            $level = null;
            foreach ($data as $datum) {
                //如果是系统任务，则直接返回
                if ($datum['Level'] == TASK_LEVEL_SYSTEM) {
                    return $datum;
                }
                if($level == null){
                    $level = $datum['Level'];
                }
                //当等级不一致时，跳出
                if($level > $datum['Level']){
                    break;
                }
                //寻找是否存在当前任务级别的设备运行的微信
                if($datum['WeixinID'] == $OnlineWeixinID){
                    if($level == $datum['Level']){
                        return $datum;
                    }
                }
            }
            //未发现符合要求的任务，返回第一个任务
            $info = array_shift($data);
            return $info;
        }
        return false;
    }

    /**
     * 获取设备新任务
     */
    public function getDeviceNewTask($DeviceID)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where("unix_timestamp() < (unix_timestamp(NextRunTime) + 3600)")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums')
            ->where('IdType = ?', 'DEVID')
            ->where('WeixinID = ?', $DeviceID)
            ->where("Status = ?", TASK_STATUS_NOTSTART)
            ->where('IsSendClient = ?', 'Y')
            ->order("Level Desc")
            ->order("AddDate Asc");
        $data = $this->_db->fetchAll($select);
        if($data) {
            $level = null;
            foreach ($data as $datum) {
                //如果是系统任务，则直接返回
                if ($datum['Level'] == TASK_LEVEL_SYSTEM) {
                    return $datum;
                }
                if($level == null){
                    $level = $datum['Level'];
                }
                //当等级不一致时，跳出
                if($level > $datum['Level']){
                    break;
                }
            }
            //未发现符合要求的任务，返回第一个任务
            $info = array_shift($data);
            return $info;
        }
        return false;
    }

    /**
     * 获取设备新任务
     */
    public function getDeviceOrWxNewTask($DeviceID, $onlineWixinId = 0)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where("unix_timestamp() < (unix_timestamp(NextRunTime) + 3600)")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums');
        if ($onlineWixinId > 0) {
            $select->where("(WeixinID = {$onlineWixinId} and IdType = 'WXID') or (WeixinID = {$DeviceID} and IdType = 'DEVID')");
        } else {
            $select->where('IdType = ?', 'DEVID')
                ->where('WeixinID = ?', $DeviceID);
        }
        $select->where("Status = ?", TASK_STATUS_NOTSTART)
        ->where('IsSendClient = ?', 'Y')
        ->order("Level Desc")
        ->order("AddDate Asc");
        $data = $this->_db->fetchAll($select);
        if($data) {
            $level = null;
            foreach ($data as $datum) {
                //如果是系统任务，则直接返回
                if ($datum['Level'] == TASK_LEVEL_SYSTEM) {
                    return $datum;
                }
                if($level == null){
                    $level = $datum['Level'];
                }
                //当等级不一致时，跳出
                if($level > $datum['Level']){
                    break;
                }
            }
            //未发现符合要求的任务，返回第一个任务
            $info = array_shift($data);
            return $info;
        }
        return false;
    }

    /**
     * 获取手机添加微信任务(进行中或未开始)
     * @return Model_Task[]
     */
    public function getPhoneAddWxTask()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums')
            ->where("CURRENT_DATE() >= StartTime")
            ->where("CURRENT_DATE() <= EndTime")
            // 未开始/进行中
            ->where('Status in (?)', [TASK_STATUS_NOTSTART, TASK_STATUS_START])
            ->where('TaskCode in (?)', [TASK_CODE_PHONE_ADD_WX,TASK_CODE_WEIXIN_ADD_WX])
            ->where('WeixinID = 0')
            ->order("Level Desc")
            ->order("AddDate Asc");
        return $this->_db->fetchAll($select);
    }

    public function getReportWxFriendsTask()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where('MaxRunNums =0 or MaxRunNums > AlreadyNums')
            // 未开始/进行中
            ->where('Status in (?)', [TASK_STATUS_NOTSTART, TASK_STATUS_START])
            ->where('TaskCode = ?', TASK_CODE_REPORT_WXFRIENDS)
            ->where('WeixinID = 0')
            ->order("Level Desc")
            ->order("AddDate Asc");
        return $this->_db->fetchAll($select);
    }


    public function getTaskByCondition($data)
    {
        $select = $this->_db->select();
        $select->from($this->_name);
        foreach($data as $key => $datum){
            switch ($key){
                case 'LastRunTime':
                    $select->where("{$key} >= ?", $datum . " 00:00:00");
                    $select->where("{$key} <= ?", $datum . " 23:59:59");
                    break;
                default:
                    $select->where("$key = ?", $datum);
            }
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 发送任务(新方法)
     */
    public function sendTask($taskInfo, $clientId, $weixin, $deviceId)
    {
        $message = [
            'TaskID' => $taskInfo['TaskID'],
            'TaskCode'  =>  $taskInfo['TaskCode'],
            'Weixin' => $weixin,
            'TaskConfig' => json_decode($taskInfo['TaskConfig'], true)
        ];

        $flag = Helper_Gateway::initConfig()->sendToClient($clientId, json_encode($message));
        if($flag){
            $this->setSend($taskInfo['TaskID'], $deviceId, $clientId);
            return true;
        }
        return false;
    }

    /**
     * 发送任务
     * @param $taskInfo
     */
    public function send($DeviceID, $taskInfo)
    {
        $weixinModel = new Model_Weixin();
        $deviceModel = new Model_Device();
        if ($taskInfo['IdType'] == 'WXID') {
            $weixinInfo = $weixinModel->getInfo($taskInfo['WeixinID']);
        }
        $deviceInfo = $deviceModel->getInfo($DeviceID);
        if (!$deviceInfo['ClientID']) {
            return false;
        }

        $message = [
            'TaskID' => $taskInfo['TaskID'],
            'TaskCode'  =>  $taskInfo['TaskCode'],
            'Weixin' => $taskInfo['IdType'] == 'WXID' ? $weixinInfo['Weixin'] : '',
            'TaskConfig' => json_decode($taskInfo['TaskConfig'], true)
        ];

        try {
            $flag = Helper_Gateway::initConfig()->sendToClient($deviceInfo['ClientID'], json_encode($message));
            if($flag){
                $this->setSend($taskInfo['TaskID'], $DeviceID, $deviceInfo['ClientID']);
                return true;
            }

        } catch (\Exception $e) {
            DM_Log::create('common')->add('err:'.$e->__toString());
            return false;
        }
    }

    public function setSend($TaskID, $deviceId = 0, $clientId = '')
    {
        $task = $this->fetchRow(["TaskID = ?" => $TaskID]);
        $task->Status = TASK_STATUS_SEND;
        $task->LastRunTime = date('Y-m-d H:i:s');
        $task->NextRunTime = $task->TaskRunTime ? self::getNextRunTime(json_decode($task->TaskRunTime, 1), $task->LastRunTime) : '';
        $task->AlreadyNums += 1;
        $task->save();

        $taskLogModel = new Model_Task_Log();
        //加入日志
        $taskLogModel->add($TaskID, 0, STATUS_NORMAL, "任务已发送给客户端,DeviceID:".$deviceId.';ClientID:'.$clientId);
    }

    public function setStart($TaskID)
    {
        $data = [
            'Status'    =>  TASK_STATUS_START,
        ];
        $where = "TaskID = '{$TaskID}'";
        $this->_db->update($this->_name, $data, $where);

        $taskLogModel = new Model_Task_Log();
        //加入日志
        $taskLogModel->add($TaskID, 0, STATUS_NORMAL, "任务开始执行");
    }

    public function setFinish($TaskID, $BodyID = "")
    {
        $data = [
            'Status'    =>  TASK_STATUS_FINISHED,
        ];
        $where = "TaskID = '{$TaskID}'";
        $this->_db->update($this->_name, $data, $where);

        $taskLogModel = new Model_Task_Log();
        //加入日志
        $taskLogModel->add($TaskID, $BodyID, STATUS_NORMAL, "任务完成");
    }

    public function setFailure($TaskID,$TaskCode,$TaskConfig,$BodyID = "", $Msg = "")
    {
        switch ($TaskCode){

            case TASK_CODE_FRIEND_JOIN:

                $phoneModel = new Model_Phones();
                $TaskConfig = json_decode($TaskConfig,1);
                $phoneModel->savePhoneState($TaskConfig['Phones']);

                break;
            case TASK_CODE_WXFRIEND_JOIN:

                $sendweixinModel = new Model_Sendweixin();
                $TaskConfig = json_decode($TaskConfig,1);
                $sendWeixin = [];
                foreach ($TaskConfig['SendWeixins'] as $s){
                    $sendWeixin[] = $s['Wx'];

                }
                if ($sendWeixin){
                    $sendweixinModel->saveSendweixinState($sendWeixin);
                }
                break;

        }
        $data = [
            'Status'    =>  TASK_STATUS_FAILURE,
        ];
        $where = "TaskID = '{$TaskID}'";
        $this->_db->update($this->_name, $data, $where);

        $taskLogModel = new Model_Task_Log();
        //加入日志
        $taskLogModel->add($TaskID, $BodyID, STATUS_ABNORMAL, $Msg);
    }

    /**
     * 重启任务
     * @param $TaskID
     */
    public function setRestart($TaskID)
    {
        $data = [
            'Status'    =>  TASK_STATUS_NOTSTART,
        ];
        $where = "TaskID = '{$TaskID}'";
        $this->_db->update($this->_name, $data, $where);

        $taskLogModel = new Model_Task_Log();
        //加入日志
        $taskLogModel->add($TaskID, 0, STATUS_NORMAL, "任务重新丢入队列");
    }

    public function checkLoop($runtime)
    {
        return strpos($runtime, '/') !== false;
    }

    public function findByID($TaskID)
    {
        $select = $this->select();
        $select->where('TaskID = ?', $TaskID);
        return $this->_db->fetchRow($select);
    }

    public function findFromSlaveTaskID($TaskID)
    {
        $select = $this->fromSlaveDB()->select();
        $select->where('TaskID = ?', $TaskID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 增加通用任务(IdType=WXID)
     */
    public static function addCommonTask($taskCode, $wxId, $taskConfig, $adminId = 0, $nextRunTime = '')
    {
        return (new Model_Task())->insert([
            'IdType' => 'WXID',
            'WeixinID' => $wxId,
            'TaskCode' => $taskCode,
            'TaskConfig' => $taskConfig,
            'MaxRunNums' => 1,
            'AlreadyNums' => 0,
            'TaskRunTime' => '',
            'ExpectTime' => '',
            'NextRunTime' => $nextRunTime ? $nextRunTime : date('Y-m-d H:i:s'),
            'LastRunTime' => '0000-00-00 00:00:00',
            'Status' => TASK_STATUS_NOTSTART,
            'IsSendClient' => 'Y',
            'AddDate' => date('Y-m-d H:i:s'),
            'AdminID' => $adminId
        ]);
    }

    public static function addDeviceTask($taskCode, $deviceId, $taskConfig, $adminId = 0, $nextRunTime = '')
    {
        return (new Model_Task())->insert([
            'IdType' => 'DEVID',
            'WeixinID' => $deviceId,
            'TaskCode' => $taskCode,
            'TaskConfig' => $taskConfig,
            'MaxRunNums' => 1,
            'AlreadyNums' => 0,
            'TaskRunTime' => '',
            'ExpectTime' => '',
            'NextRunTime' => $nextRunTime ? $nextRunTime : date('Y-m-d H:i:s'),
            'LastRunTime' => '0000-00-00 00:00:00',
            'Status' => TASK_STATUS_NOTSTART,
            'IsSendClient' => 'Y',
            'AddDate' => date('Y-m-d H:i:s'),
            'AdminID' => $adminId
        ]);
    }


    /**
     * 查询指定WeixinIDs的发送好友请求数据
     * @param array $WeixinIDs 微信IDs
     */
    public function findWeixinSendFriendNum($WeixinIDs,$Day)
    {
        $select = $this->select()
            ->from($this->_name,['WeixinID','TaskResult','UpdateDate'])
            ->where("TaskCode = 'FriendJoin'")
            ->where("WeixinID in (?)", $WeixinIDs)
            ->where("LastRunTime > ?",$Day)
            ->where("LastRunTime < ?",date('Y-m-d',strtotime($Day.' +1 day')));
        $data = $this->_db->fetchAll($select);

        $num = [
            'SendNum'=>0,
            'NotWeixin'=>0,
            'IsFriends'=>0,
            'AddNum'=>0
        ];
        foreach ($data as &$val){
            $TaskResult = json_decode($val['TaskResult'],1);
            if ($val){
                $num['SendNum'] += $TaskResult['SendSuccess'];
                $num['NotWeixin'] += $TaskResult['NotWeixin'];
                $num['IsFriends'] += count($TaskResult['AddFriendNum']);
                $num['AddNum'] += $TaskResult['AddFriendNum'];
            }
        }
        return $num;

    }

    /**
     * 任务详情
     * @return array
     */
    public function findTaskInfo($TaskID)
    {
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->from($this->_name.' as t',['t.TaskID','t.TaskConfig','t.TaskCode','t.StartTime','t.EndTime','t.LastRunTime','t.NextRunTime'])
            ->joinLeft('weixins as w','w.WeixinID = t.WeixinID',['w.Nickname','w.Weixin'])
            ->where('t.TaskID = ?',$TaskID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询子任务
     */
    public function findChild($TaskID)
    {
        $select = $this->select()->where('ParentTaskID = ?',$TaskID);
        return $this->_db->fetchAll($select);
    }

    /**
     * 手机添加微信好友指定数量查询
     */
    public function findPhoneAddWxChild($taskId,$page,$pagesize)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,['TaskID','TaskConfig','LastRunTime'])->where('ParentTaskID = ?',$taskId);
        $data = $this->getResult($select,$page,$pagesize);
        return empty($data)?false:$data['Results'];
    }


    public function findWeixin($day)
    {
        $select = $this->select()
            ->from($this->_name.' as t','')
            ->setIntegrityCheck(false)
            ->join('weixins as w','w.WeixinID = t.WeixinID',['w.WeixinID','w.FriendNumber'])
            ->where('t.WeixinID > 19')
            ->where('t.LastRunTime < ?',date('Y-m-d',strtotime($day.' +1 day')))
            ->group('t.WeixinID');
        return $this->_db->fetchAll($select);
    }


    /**
     * 手机添加好友查询子任务
     */
    public function findFinishedChild($TaskID)
    {
        $select = $this->select()->where('ParentTaskID = ?',$TaskID)->where('Status in (?)',[TASK_STATUS_START,TASK_STATUS_FINISHED])->where('TaskResult <> ""');
        $data = $this->_db->fetchAll($select);

        $res = [
            'SendNum'=>0,
            'SendSuccess'=>0,
            'NotWeixin'=>0,
            'NoSend'=>0,
            'SendFail'=>0,
        ];
        foreach ($data as $v){
            $tesk_result = json_decode($v['TaskResult'],1);
            $res['SendNum'] += $tesk_result['SendNum'];
            $res['SendSuccess'] += $tesk_result['SendSuccess'];
            $res['NotWeixin'] += $tesk_result['NotWeixin'];
            $res['NoSend'] += $tesk_result['NoSend'];
            $res['SendFail'] += $tesk_result['SendFail'];
        }
        return $res;
    }

    /**
     * 微信添加好友查询子任务
     */
    public function findWxFinishedChild($TaskID)
    {
        $select = $this->select()->where('ParentTaskID = ?',$TaskID)->where('Status = ?',TASK_STATUS_FINISHED)->where('TaskResult <> ""');
        $data = $this->_db->fetchAll($select);

        $res = [
            'SendNum'=>0,
            'SendSuccess'=>0,
            'SendFail'=>0,
        ];
        foreach ($data as $v){
            $tesk_result = json_decode($v['TaskResult'],1);
            $res['SendNum'] += $tesk_result['SendNum'];
            $res['SendSuccess'] += $tesk_result['SendSuccess'];
            $res['SendFail'] += $tesk_result['SendFail'];
        }
        return $res;
    }

    /**
     * 查询当日已经上报过微信好友信息的WeixinID
     *
     */
    public function findReportWxTask()
    {
        $date = date('Y-m-d');
        $hour = date('H');
        $select = $this->select()->from($this->_name,'WeixinID');
        $select->where('TaskCode = ?',TASK_CODE_WEIXIN_FRIEND);
        // 6点之前的任务值统计前一天的
        if ($hour < 6){
            $select->where('LastRunTime > ?',$date.' 00:00:00');
            $select->where('LastRunTime <= ?',$date.' 06:00:00');
        }else{
            $select->where('LastRunTime > ?',$date.' 06:00:00');
            $select->where('LastRunTime <= ?',$date.' 23:59:59');
        }
        $select->where('TaskConfig like ?','%[]%');
        $select->where('Status = 4');
        $data = $this->_db->fetchAll($select);

        $res = array();
        foreach ($data as $v){
            $res[] = $v['WeixinID'];
        }

        return $res;
    }

    /**
     * 加锁查询
     */
    public function findForTask($TaskID)
    {
        $sql = "select TaskID,TaskResult from `tasks` where TaskID = ? for update";
        return $this->_db->fetchRow($sql,$TaskID);
    }

    /**
     * 寻找该任务下未成功执行的子任务
     * @param $TaskID
     * @return array
     */
    public function findStatusAction($ParentTaskID)
    {
        $select = $this->select()
            ->where('ParentTaskID = ?',$ParentTaskID)
            ->where('Status  <> 4');
        return $this->_db->fetchAll($select);
    }

    /**
     * 今天执行过指定任务的微信号
     *
     * @param $WeixinIDs 微信Ids
     * @param $TaskCode  任务ID
     */
    public function todayRunTasks($WeixinIDs,$TaskCode,$Status = null)
    {
        $date = date('Y-m-d');
        $select = $this->select()->from($this->_name,['WeixinID']);
        $select->where('WeixinID in (?)',$WeixinIDs);
        $select->where('TaskCode = ?',$TaskCode);
        $select->where('LastRunTime >= ?',$date.' 00:00:00');
        $select->where('LastRunTime <= ?',$date.' 23:59:59');
        if ($Status){
            $select->where('Status = ?',$Status);
        }
        $select->group('WeixinID');
        $data = $this->_db->fetchAll($select);
        $res = [];

        foreach ($data as $d){
            $res[] = $d['WeixinID'];
        }

        return $res;
    }

    /**
     * 统计微信ID的任务类别数量
     * @param $WeixinID
     * @param $startTime
     * @param $stopTime
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function statTaskNum($WeixinID, $startTime, $stopTime)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["TaskCode","count(*) as num"])
               ->where("WeixinID = ?", $WeixinID)
               ->where("LastRunTime >= ?", $startTime)
               ->where("LastRunTime <= ?", $stopTime)
               ->group("TaskCode");
        return $this->fromSlaveDB()->fetchAll($select)->toArray();
    }

    /**
     * 获取某个时段的随机时间
     */
    public function getRandomTime($Day = '',$StartHour = 0,$EndHour = 24)
    {
        if (empty($Day)){
            $Day = date('Y-m-d');
        }
        // 充电
        $h = (string)rand($StartHour,$EndHour);
        $i = (string)rand(0,59);
        $s = (string)rand(0,59);
        if ((int)$h<10){
            $h = '0'.$h;
        }
        if ((int)$i<10){
            $i = '0'.$i;
        }
        if ((int)$s<10){
            $s = '0'.$s;
        }
        $time = $Day.' '.$h.':'.$i.':'.$s;

        return $time;
    }
}