<?php
/**
 * 手机添加微信
 */
class TaskRun_Common extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='common';

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
        $deviceModel = new Model_Device();
        $taskModel = new Model_Task();

        // 类型: 定时执行一次(如每天) / 循环执行(每隔多长时间) / 一次
        //寻找空闲设备
        $deviceData = $deviceModel->getFree();

//        $onlineWxIds = [];
//        $deviceIds = [];
//        $clientIds = [];
//        $weixins = [];
//        foreach ($deviceData as $d) {
//            $onlineWxIds[] = $d['OnlineWeixinID'];
//            $deviceIds[$d['OnlineWeixinID']] = $d['DeviceID'];
//            $clientIds[$d['OnlineWeixinID']] = $d['ClientID'];
//            $weixins[$d['OnlineWeixinID']] = $d['OnlineWeixin'];
//        }
//
//        if ($onlineWxIds) {
//            $tasks = $taskModel->getNewTasksByOnlineWxIds($onlineWxIds, 100);
//
//            $clientIds = [];
//            foreach ($tasks as $taskInfo) {
//                $deviceId = isset($deviceIds[$taskInfo['WeixinID']]) ? $deviceIds[$taskInfo['WeixinID']] : 0;
//                $clientId = isset($clientIds[$taskInfo['WeixinID']]) ? $clientIds[$taskInfo['WeixinID']] : '';
//                $weixin = isset($weixins[$taskInfo['WeixinID']]) ? $weixins[$taskInfo['WeixinID']] : '';
//                if ($deviceId > 0 && $clientId !== '' && $weixin !== '') {
//                    $taskModel->sendTask($taskInfo, $clientId, $weixin, $deviceId);
//                }
//            }
//        }

        //寻找这些设备的任务
        foreach($deviceData as $deviceDatum){
            /*if (empty($deviceDatum['OnlineWeixinID'])) {
                $taskInfo = $taskModel->getDeviceNewTask($deviceDatum['DeviceID']);
            } else {
                $taskInfo = $taskModel->getNewTask($deviceDatum['DeviceID'], $deviceDatum['OnlineWeixinID']);
            }*/
            $onlineWeixinId = (int)$deviceDatum['OnlineWeixinID'];
            $taskInfo = $taskModel->getDeviceOrWxNewTask((int)$deviceDatum['DeviceID'], $onlineWeixinId);
            if(!isset($taskInfo['TaskID'])){
                continue;
            }

            $r = $taskModel->send($deviceDatum['DeviceID'], $taskInfo);
            if ($r === false) {
                self::getLog()->add('task send err, taskId:'.$taskInfo['TaskID']);
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
