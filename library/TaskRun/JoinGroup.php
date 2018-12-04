<?php
/**
 * 加群任务
 */
class TaskRun_JoinGroup extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='joinGroup';

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
        try{
            // Model
            $group_join_model = new Model_Group_Join();
            $weixin_model = new Model_Weixin();
            $group_model = new Model_Group();
            $task_model = new Model_Task();

            // 查询可执行的创建任务
            $group_join = $group_join_model->getJoinGroup();


            foreach ($group_join as $val){

                // 获取标签下的所有微信号
                $weixins = $weixin_model->findWeixinCategory($val['WeixinTags']);

                // 获取群信息
                $chatroom = array();
                $groups = $group_model->findGroups($val['GroupTags']);
                foreach ($groups as $g){
                        $chatroom[] = $g['QRCodeImg'];
                }

                $task_model->getAdapter()->beginTransaction();
                foreach($weixins as $wx){
                    $task_config = [
                        'Code'=>$chatroom
                    ];
                    $task_model->insert([
                        'WeixinID' => $wx['WeixinID'],
                        'TaskCode' => TASK_CODE_GROUP_JOIN,
                        'TaskConfig' => json_encode($task_config),
                        'MaxRunNums' => 1,
                        'AlreadyNums' => 0,
                        'TaskRunTime' => '',
                        'NextRunTime' => date('Y-m-d H:i:s',strtotime('+3 minute')),
                        'LastRunTime' => '0000-00-00 00:00:00',
                        'Status' => TASK_STATUS_NOTSTART,
                        'ParentTaskID' => 0,
                        'IsSendClient' => 'Y'
                    ]);
                }
                $nextRunTime = $task_model::getNextRunTime(json_decode($val['JoinTime'],1), $val['LastRunTime']);
                if ($nextRunTime > ($val['EndTime'] . ' 23:59:59')) {
                    $nextRunTime = '0000-00-00 00:00:00';
                }
                // 记录最后的编号
                $group_join_model->update(['NextRunTime'=>$nextRunTime,'LastRunTime'=>date('Y-m-d H:i:s')],['JoinID = ?'=>$val['JoinID']]);
                $task_model->getAdapter()->commit();
            }
        }catch(Exception $e){
            $task_model->getAdapter()->rollBack();
            self::getLog()->add('cron generate child task err:' . $e->__toString());
            self::getLog()->flush();
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
