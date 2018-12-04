<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/9/29
 * Time: 15:20
 * 微信好友群发消息
 */
class TaskRun_FriendGroupSend extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='friendGroupSend';

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
        // Model
        $fsModel = new Model_Weixin_FriendGroupsend();
        $weixin_model = new Model_Weixin();
        $task_model = new Model_Task();
        $weixin_friend_model = new Model_Weixin_Friend();

        // 查询可执行的群发任务
        $groupSends = $fsModel->select()->where('Status = ?', Model_Weixin_FriendGroupsend::STATUS_PENDING)->where('SendTime <= ?', date('Y-m-d H:i:s'))->where('SendTime >= ?', date('Y-m-d H:i:s', strtotime('-15 minutes')))->query()->fetchAll();

        foreach ($groupSends as $groupSend){

            $weixin_ids = []; //微信号ID
            $friendtag_ids = []; //好友标签ID
            $delWx_ids = []; //排除微信号ID
            $sendWeixin = []; //待发送的微信号以及好友信息
            // 获取标签下的所有微信号
            if($groupSend['WeixinTags'] != ''){
                $weixins = $weixin_model->findWeixinCategory($groupSend['WeixinTags']);
                if ($weixins) {
                    foreach ($weixins as $v){
                        $weixin_ids[] = $v['WeixinID'];
                    }
                }
            }
            if($groupSend['FriendTags'] != ''){
                $friendtag_ids = array_unique(explode(',', $groupSend['FriendTags']));
            }
            if($groupSend['DelWeixinIDs'] != ''){
                $delWx_ids = array_unique(explode(',', $groupSend['DelWeixinIDs']));
            }
            if(empty($weixin_ids) && empty($friendtag_ids)){
                self::getLog()->add('此群发任务未指定微信号和好友标签,GroupSendID:' . $groupSend['GroupSendID']);
                continue;
            }
            $select = $weixin_friend_model->getSelectByQuery(array_unique($weixin_ids), $friendtag_ids, $delWx_ids);
            $friends = $select->query()->fetchAll();
            //查询微信号和好友数据
            if(empty($friends)){
                self::getLog()->add('此群发任务未查询到可发送微信号和好友,GroupSendID:' . $groupSend['GroupSendID']);
                continue;
            }

            foreach($friends as $wx){
                if($wx['Account'] == ''){
                    self::getLog()->add('未查询到此好友关系信息中的好友微信号,FriendID:' . $wx['FriendID']);
                    continue;
                }
                $sendWeixin[$wx['WeixinID']][] = $wx['Account'];
            }
            try{
                $task_model->getAdapter()->beginTransaction();
                //生成task任务
                $taskIDs = [];
                foreach ($sendWeixin as $key => $val){
                    $task_config = [
                        'Weixins' => $val,
                        'Content' => json_decode($groupSend['Content'], true),
                    ];
                    $taskIDs[] = Model_Task::addCommonTask(TASK_CODE_FRIEND_GROUP_SEND, $key, json_encode($task_config), $groupSend['AdminID']);
                }
                //更新群发任务信息
                $fsModel->update(['Status'=> Model_Weixin_FriendGroupsend::STATUS_COMPLETED,'TaskIDs'=>implode(',', $taskIDs)],['GroupSendID = ?'=> $groupSend['GroupSendID']]);
                $task_model->getAdapter()->commit();

            }catch (Exception $e){
                $task_model->getAdapter()->rollBack();
                self::getLog()->add('cron generate child task err:' . $e->__toString());
                self::getLog()->flush();
            }

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
