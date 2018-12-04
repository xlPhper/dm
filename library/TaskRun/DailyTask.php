<?php
/**
 * 微信的每日任务
 *
 * by Ekko
 */
class TaskRun_DailyTask extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='dailyTask';

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
        $dailyTaskModel = new Model_DailyTask();
        $taskModel = new Model_Task();

        $dailyTasks = $dailyTaskModel->findAllDailyTask();

        foreach ($dailyTasks as $task){
            try{
                $taskModel->getAdapter()->beginTransaction();

                $taskModel->insert([
                    'WeixinID' => $task['WeixinID'],
                    'TaskCode' => $task['TaskCode'],
                    'TaskConfig' => json_encode([]),
                    'MaxRunNums' => 1,
                    'AlreadyNums' => 0,
                    'TaskRunTime' => '',
                    // 当前时间向后推迟 5-30 秒
                    'NextRunTime' => $task['NextRunTime'],
                    'LastRunTime' => '0000-00-00 00:00:00',
                    'Status' => TASK_STATUS_NOTSTART,
                    'ParentTaskID' => 0,
                    'IsSendClient' => 'Y',
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
                $h = (string)rand(1,24);
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
                $pdateData = [
                    'NextRunTime'=>date('Y-m-d ',strtotime('+1 day')).$h.':'.$i.':'.$s,
                    'LastRunTIme'=>date('Y-m-d H:i:s')
                ];
                $dailyTaskModel->update($pdateData,['DailyTaskID = ?'=>$task['DailyTaskID']]);

                $taskModel->getAdapter()->commit();

            }catch (Exception $e){
                $taskModel->getAdapter()->rollBack();
                self::getLog()->add('cron generate child task err:' . $e->__toString());
                self::getLog()->flush();
            }
        }
        exit();
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
