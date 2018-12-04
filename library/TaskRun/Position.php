<?php
/**
 * 随机定位
 * Ekko
 */
class TaskRun_Position extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='position';

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

        $positionWeixinModel= new Model_PositionWeixin;
        $positionModel = new Model_Position();
        $taskModel = new Model_Task();

        $onlineWeixinIds =  (new Model_Device())->findOnlineWeixin();

        $positionTask = $positionWeixinModel->getRunTask();


        foreach ($positionTask as $t) {

            try {
                $taskModel->getAdapter()->beginTransaction();

                $weixinIds = explode(',',$t['Weixins']);
                // 在线可执行
                $weixinIds = array_intersect($weixinIds,$onlineWeixinIds);
                shuffle($weixinIds);

                if ($weixinIds){

                    $positions = $positionModel->findByTagID($t['PositionTagID']);

                    foreach ($positions as $p){
                        $wxId = array_pop($weixinIds);
                        if ($wxId){
                            $childTaskConfigs = [
                                'Longitude' => $p['Longitude'],
                                'Latitude' => $p['Latitude']
                            ];

                            $taskModel->addCommonTask(TASK_CODE_RANDOM_POSITION, $wxId, json_encode($childTaskConfigs), 0);

                            $positionModel->update(['WeixinID'=>$wxId],['PositionID = ?'=>$p['PositionID']]);

                        }
                    }

                }
                
                $tmpExecTime = json_decode($t['ExecTime'], 1);

                list($nextRunTime, $nextRunType) = Helper_Timer::getNextRunTime($t['StartDate'], $t['EndDate'], $tmpExecTime);

                $taskData = [
                    'ExecutedNums'=>$t['ExecutedNums']+1,
                    'NextRunTime'=>$nextRunTime,
                    'NextRunType'=>$nextRunType,
                    'LastRunTime'=>date('Y-m-d H:i:s')
                ];
                $positionWeixinModel->update($taskData,['PositionWxID = ?'=>$t['PositionWxID']]);

                $taskModel->getAdapter()->commit();

            } catch (Exception $e) {
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
