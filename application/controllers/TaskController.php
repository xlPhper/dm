<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/26
 * Time: 14:18
 */


class TaskController extends DM_Controller
{
    public function init()
    {
        parent::init();
    }

    /**
     * 消息发送服务
     */
    public function msgSendSerAction()
    {
        TaskRun_MsgSendSer::instance()->daemonRun();
    }

    /**
     * 更新每日发送好友数
     *
     */
    public function statAction()
    {
        TaskRun_Stat::instance()->daemonRun();
    }

    /**
     * socket 检查
     */
    public function socketCheckAction()
    {
        TaskRun_SocketCheck::instance()->daemonRun();
    }

    /**
     * 排期管理
     */
    public function scheduleAction()
    {
        TaskRun_Schedule::instance()->daemonRun();
    }

    /**
     * 手机添加微信号任务
     */
    public function phoneAddWxAction()
    {
        TaskRun_PhoneAddWx::instance()->daemonRun();
    }


    /**
     * 循环寻找可执行任务
     */
    public function runAction()
    {
//        $category = (new Model_Category())->fetchRow(['CategoryID = ?' => 77]);
//        var_dump($category ? $category->toArray() : []);
//        exit;
        TaskRun_Common::instance()->daemonRun();
    }

    /**
     * 上报微信群信息
     */
    public function reportWxGroupsAction()
    {
        TaskRun_ReportWxGroups::instance()->daemonRun();
    }

    /**
     * 上报微信好友
     */
    public function reportWxFriendsAction()
    {
        TaskRun_ReportWxFriends::instance()->daemonRun();
    }

    /**
     * 创建微信群
     */
    public function createGroupAction()
    {
        TaskRun_CreateGroup::instance()->daemonRun();
    }

    /**
     * 加入微信群
     */
    public function joinGroupAction()
    {
        TaskRun_JoinGroup::instance()->daemonRun();
    }

    /**
     * 检测手机微信
     */
    public function detectionPhonesAction()
    {
        TaskRun_DetectionPhones::instance()->daemonRun();
    }

    /**
     * 检测手机微信
     */
    public function positionAction()
    {
        TaskRun_Position::instance()->daemonRun();
    }

    /**
     * 好友群发任务检查
     */
    public function friendGroupSendAction()
    {
        TaskRun_FriendGroupSend::instance()->daemonRun();
    }

    /**
     * 检测群二维码图片处理
     */
    public function checkGroupQrcodeAction()
    {
        TaskRun_CheckGroupQrcode::instance()->daemonRun();
    }

    /**
     * 每小时统计派单数据
     * @throws Exception
     */
    public function distributionStatAction()
    {
        TaskRun_DistributionStat::instance()->daemonRun();
    }

    /**
     * 微信号每日任务
     * @throws Exception
     */
    public function dailyAction()
    {
        TaskRun_DailyTask::instance()->daemonRun();
    }

    public function adAction()
    {
        TaskRun_Ad::instance()->daemonRun();
    }

    /**
     * 解析二维码html
     */
    public function qrGatherAnalyseAction()
    {
        TaskRun_QrGatherAnalyse::instance()->daemonRun();
    }

    /**
     * 开始抓取二维码任务
     */
    public function qrGatherStartAction()
    {
        TaskRun_QrGatherStart::instance()->daemonRun();
    }

    /**
     * 接收到任务后调用
     */
    public function startAction()
    {
        $TaskID = intval($this->_getParam('TaskID'));

        if($TaskID) {
            $taskModel = new Model_Task();
            $taskModel->setStart($TaskID);
        }
        $this->showJson(1);
    }

    /**
     * 同步聊天信息到阿里云的开放搜索
     *
     */
    public function messageSyncAliyunAction()
    {
        TaskRun_MessageSyncAliyun::instance()->daemonRun();
    }


    /**
     * 任务完成后客户端调用接口
     */
    public function doneAction()
    {
        $TaskID = $this->_getParam('TaskID');
        $Status = $this->_getParam('Status');
        $Note = $this->_getParam('Note');
        $BodyID = $this->_getParam('BodyID');

        $taskModel = new Model_Task();

        $taskInfo = $taskModel->getInfo($TaskID);
        if(!isset($taskInfo['TaskID'])){
            $this->showJson(0, 'taskid not exist');
        }
        try {
            //如果存在ID，则表示执行体ID存在
            if($BodyID){
                //设置任务成功失败数量
                if($Status == STATUS_NORMAL){
                    $taskInfo['SuccessNum']++;
                }else{
                    $taskInfo['FailureNum']++;
                }

                $FinishID = json_decode($taskInfo['FinishID'], true);
                if(!in_array($BodyID, $FinishID)){
                    $FinishID[] = $BodyID;
                }
                $taskInfo['FinishID'] = json_encode($FinishID);

                //如果成功数量+失败数量=总数量，则表示任务完成
                if($taskInfo['SuccessNum'] + $taskInfo['FailureNum'] == $taskInfo['TotalNum']){
                    $taskInfo['Status'] = $taskInfo['SuccessNum'] > $taskInfo['FailureNum'] ? TASK_STATUS_FINISHED : TASK_STATUS_FAILURE;
                }
            }else{
                $taskInfo['Status'] = $Status == STATUS_NORMAL ? TASK_STATUS_FINISHED : TASK_STATUS_FAILURE;
            }

            if($taskInfo['Status'] == TASK_STATUS_FINISHED){
                $taskModel->setFinish($taskInfo['TaskID'], $BodyID);
            }else{
                $taskModel->setFailure($taskInfo['TaskID'],$taskInfo['TaskCode'],$taskInfo['TaskConfig'],$BodyID, $Note);
            }

            //判断任务是否为循环任务
            if($taskModel->checkLoop($taskInfo['TaskRunTime'])){
                //是循环任务，则重新丢入任务队列
                $taskModel->setRestart($taskInfo['TaskID']);
            }

            $this->showJson(1, 'task ok');
        } catch (\Exception $e) {
            $this->showJson(0, 'task err:'.$e->getMessage());
        }

    }

    public function initDoubanGatherAction(){
        try{
            set_time_limit(0);
//                $model = new Model_Linkurl();
//                $data = $model->getQuerySelect(['Status' => Model_Linkurl::STATUS_SEND], false)->order('LinkurlID Asc')->limit(2)->query()->fetchAll();
//                //找到待解析的地址数据
//                if(!empty($data)){
//                    $doubanModel = new Model_Gather_Douban();
//                    foreach ($data as $row){
//                        if(empty($row['Html'])){
//                            continue;
//                        }
//                        //豆瓣地址处理
//                        if($row['Channel'] == Model_Linkurl::GATHER_CHANNEL_DOUBAN){
//                            if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_SEARCH){
//                                $doubanModel->searchHtmlDeal($row['Html']);
//                            }else if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_LIST){
//                                $doubanModel->discussionListHtmlDeal($row['Html']);
//                            }else if($row['Type'] == Model_Linkurl::GATHER_URL_TYPE_DETAIL){
//                                $doubanModel->detailHtmlDeal($row['Html']);
//                            }
//                        }
//                        //更新地址状态为已解析
//                        $model->update(['Status' => Model_Linkurl::STATUS_GATHER], ['LinkurlID = ?' => $row['LinkurlID']]);
//                    }
//                }
            (new Model_Gather_Douban())->initGatherTask();
            $this->showJson(1,'ok');
        }catch (Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }
    /**
     * 二维码加群
     */
    public function qrJoinAction()
    {
        try {
            TaskRun_QrJoinGroup::instance()->daemonRun();
        } catch (Exception $e) {
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * @throws Exception
     * 采集网页的html内容
     */
    public function gatherHtmlAction()
    {
        TaskRun_GatherHtml::instance()->daemonRun();
    }

    /**
     * @throws Exception
     * 每天凌晨更新好友互动频率数据
     */
    public function friendChatrateStatAction()
    {
        TaskRun_FriendChatrateStat::instance()->daemonRun();
    }

    /**
     * @throws Exception
     * 养号任务 - 每天凌晨
     */
    public function trainTaskAction()
    {
        TaskRun_TrainTask::instance()->daemonRun();
    }
    /**
     * 下发资源任务
     */
    public function resourceSyncAction()
    {
        TaskRun_ResourceSync::instance()->daemonRun();
    }

    /**
     * 下发手机充电/断电任务
     */
    public function actionPowerAction()
    {
        TaskRun_ActionPower::instance()->daemonRun();
    }
}