<?php
/**
 * 检测手机号
 */
class TaskRun_DetectionPhones extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='detectionPhones';

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
        $phone_model = new Model_Phones();
        $device_model = new Model_Device();
        $task_model = new Model_Task();
        $weixin_model = new Model_Weixin();

        // 在线可执行任务的微信号 并排除掉不给予发布任务的微信号
        $un_weixins = $weixin_model->findIsWeixins(561);
        $weixinIds = [];
        foreach ($un_weixins as $w){
            $weixinIds[] = $w['WeixinID'];
        }

        $weixin = $device_model->getDetectionPhoneWx($weixinIds);

        foreach ($weixin as $wx){
            $phones = $phone_model->findDetectionPhone(1);
            if ($phones){
                foreach ($phones as $p){
                    try{
                        $task_model->getAdapter()->beginTransaction();

                        $TaskConfig = [
                            // 单个
                            'Phones'=>$p
                        ];

                        $task_id = $task_model->insert([
                            'WeixinID' => $wx['OnlineWeixinID'],
                            'TaskCode' => TASK_CODE_DETECTION_PHONE,
                            'TaskConfig' => json_encode($TaskConfig),
                            'MaxRunNums' => 1,
                            'AlreadyNums' => 0,
                            'TaskRunTime' => '',
                            // 当前时间向后推迟 5-30 秒
//							'NextRunTime' => date('Y-m-d H:i:s', (time() + mt_rand(5, 1800))),
                            'NextRunTime' => date('Y-m-d H:i:s'),
                            'AddDate' => date('Y-m-d H:i:s'),
                            'LastRunTime' => '0000-00-00 00:00:00',
                            'Status' => TASK_STATUS_NOTSTART,
                            'ParentTaskID' => 0,
                            'IsSendClient' => 'Y'
                        ]);


                        $taskLog_model = new Model_Task_Log();
                        //加入日志
                        $taskLog_model->add($task_id, 0, STATUS_NORMAL, "生成手机微信检测任务");

                        $task_model->getAdapter()->commit();

                    }catch (Exception $e){
                        self::getLog()->add('cron generate child task err:' . $e->__toString());
                        self::getLog()->flush();
                    }
                }
                $phone_model->update(['Detection'=>1],['Phone in (?)'=>$phones]);
            }
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
