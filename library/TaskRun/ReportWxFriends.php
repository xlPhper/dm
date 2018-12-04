<?php
/**
 * 上报微信好友
 */
class TaskRun_ReportWxFriends extends DM_Daemon
{   
    const CRON_SLEEP = 1000000;
	const SERVICE='reportWxFriends';
	
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
        // {"wxIds\":"6,2"}
        $taskModel = new Model_Task();
//        $weixinModel = new Model_Weixin();
            try {
                // 在线的微信号
                $online_weixinIds =  (new Model_Device())->findOnlineWeixin();

                foreach ($online_weixinIds  as $wxId){
//                    $weixin_info = $weixinModel->getDataByWeixinID($wxId);
                    $taskModel->insert([
                        'WeixinID' => $wxId,
                        'TaskCode' => TASK_CODE_WEIXIN_FRIEND,
                        'TaskConfig' => json_encode(['Weixin' => []]),
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
                }
            } catch (\Exception $e) {
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
