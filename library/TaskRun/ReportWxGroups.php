<?php
/**
 * 上报微信群信息
 */
class TaskRun_ReportWxGroups extends DM_Daemon
{   
    const CRON_SLEEP = 1000000;
	const SERVICE='reportWxGroups';
	
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
        $taskModel = new Model_Task();
        $weixinModel = new Model_Weixin();
        $taskLogModel = new Model_Task_Log();

        try {
            $taskModel->getAdapter()->beginTransaction();
            // 在线的微信号
            $online_weixinIds =  (new Model_Device())->findOnlineWeixin();

            // 任务的微信号
            $task_weixinIds = $weixinModel->findWeixinAllID();

            // 选出能在线执行任务的微信号
            $weixinIds = array_intersect($online_weixinIds, $task_weixinIds);

            foreach ($weixinIds  as $wxId){
                $taskID = $taskModel->insert([
                    'WeixinID' => $wxId,
                    'TaskCode' => TASK_CODE_REPORT_WXGROUPS,
                    'TaskConfig' => json_encode(['Chatroom' => array()]),
                    'MaxRunNums' => 1,
                    'AlreadyNums' => 0,
                    'TaskRunTime' => '',
                    // 当前时间向后推迟 5-30 秒
                    'NextRunTime' => date('Y-m-d H:i:s', (time() + mt_rand(5, 1800))),
                    'LastRunTime' => '0000-00-00 00:00:00',
                    'Status' => TASK_STATUS_NOTSTART,
                    'ParentTaskID' => 0,
                    'IsSendClient' => 'Y',
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
                //加入日志
                $taskLogModel->add($taskID, 0, STATUS_NORMAL, "已生成上报微信群信息子任务");
            }

            $taskModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $taskModel->getAdapter()->rollBack();
            self::getLog()->add('cron generate child task err:' . $e->__toString());
            self::getLog()->flush();
        }
        exit;
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
