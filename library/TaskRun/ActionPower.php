<?php
/**
 * Created by PhpStorm.
 * User: ekko
 * Date: 2018/11/23
 * Time: 13:25
 * 手机充电/断电任务
 */
class TaskRun_ActionPower extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='actionPower';

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

        $deviceModel = Model_Device::getInstance();
        $taskModel   = Model_Task::getInstance();
        $taskLogModel = Model_Task_Log::getInstance();


        $onlineWeixinIds =  $deviceModel->findOnlineWeixin();

        // 今日已经执行过的充电任务
        $reportWxIds = $taskModel->todayRunTasks($onlineWeixinIds,TASK_CODE_ACTION_POWER,null);

        // 筛选出在线不重复执行的微信号
        $weixinIds = array_diff($onlineWeixinIds,$reportWxIds);

        foreach ($weixinIds as $wxId){
            // 充电
            try{
                $taskModel->getAdapter()->beginTransaction();

                $connectedTime = $taskModel->getRandomTime(date('Y-m-d'),19,23);

                $connected = '{"intent":{"action":"android.intent.action.ACTION_POWER_CONNECTED"}}';

                $taskId1 = $taskModel->addCommonTask(TASK_CODE_ACTION_POWER, $wxId, $connected,0,$connectedTime);

                $taskLogModel->add($taskId1, 0, STATUS_NORMAL, "生成手机充电任务");
                $taskModel->getAdapter()->commit();

            }catch (Exception $e){
                $taskModel->getAdapter()->rollBack();
                self::getLog()->add('cron generate child task err:' . $e->__toString());
                self::getLog()->flush();
            }

            // 断电
            try{
                $taskModel->getAdapter()->beginTransaction();

                $disconnectedTime = $taskModel->getRandomTime(date('Y-m-d',strtotime('+1 day')),7,9);

                $disconnected = '{"intent":{"action":"android.intent.action.ACTION_POWER_DISCONNECTED"}}';

                $taskId2 = $taskModel->addCommonTask(TASK_CODE_ACTION_POWER, $wxId, $disconnected,0,$disconnectedTime);

                $taskLogModel->add($taskId2, 0, STATUS_NORMAL, "生成手机断点任务");
                $taskModel->getAdapter()->commit();
            }catch (Exception $e){
                $taskModel->getAdapter()->rollBack();
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