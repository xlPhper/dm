<?php
/**
 * 创建群
 */
class TaskRun_CreateGroup extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='createGroup';

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
        $group_create_model = new Model_Group_Create();
        $weixin_model = new Model_Weixin();
        $task_model = new Model_Task();
        $weixin_friend_model = new Model_Weixin_Friend();

        // 查询可执行的创建任务
        $group_create = $group_create_model->getCreateGroup();

        foreach ($group_create as $val){
            try{
                // 获取标签下的所有微信号
                $weixins = $weixin_model->findWeixinCategory($val['WeixinTags']);

                foreach($weixins as $wx){
                    $task_model->getAdapter()->beginTransaction();


                    $weixin_friend = $weixin_friend_model->findWeixinFirendWx($wx['WeixinID']);

                    $start_num = $val['StartNum']; // 获得起始编号
                    $task_config = [
                        'Weixin'=>$wx['Weixin'],
                        'Friend'=>$weixin_friend,
                        'Name'=>str_replace("%d",$start_num,$val['Name'])
                    ];
                    $task_model->insert([
                        'WeixinID' => $wx['WeixinID'],
                        'TaskCode' => TASK_CODE_GROUP_CREATE,
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
                    $start_num ++;
                }
                // 记录最后的编号
                $group_create_model->update(['StartNum'=>$start_num,'LastRunTime'=>date('Y-m-d H:i:s')],['CreateID = ?'=>$val['CreateID']]);
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
