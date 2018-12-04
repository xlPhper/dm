<?php

/**
 * schedule
 */
class TaskRun_Ad extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE = 'ad';

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
        $adModel = new Model_Ad();
        $wxModel = new Model_Weixin();
        $taskModel = new Model_Task();

        $select = $adModel->select()
            ->where('NextRunTime > LastRunTime')
            ->where('CURRENT_TIMESTAMP() >= NextRunTime')
            ->where('Status = ?', 'PROCESS')
            ->where('DeleteTime = ?', '0000-00-00 00:00:00');
        $ads = $adModel->fetchAll($select);

        $dModel = new Model_Device();
        $onlineWxIds = $dModel->findOnlineWeixin();
        $onlineDeviceIds = $dModel->getOnlineDeviceIds();

        foreach ($ads as $ad) {
            $this->getLog()->add('adid:' . $ad->AdID);
            try {
                $adModel->getAdapter()->beginTransaction();

                $taskConfig = [
                    'AdID' => $ad->AdID,
                    'Type' => $ad->Type,
                    'Links' => $ad->Links,
                    'ClickWordNum' => $ad->ClickWordNum,
                    'ClickAdPos' => $ad->ClickAdPos,
                    'ClickLike' => $ad->ClickLike,
                    'ClickUrls' => $ad->ClickUrls
                ];
                // 循环写入 task
                $oldTaskIds = $ad->TaskIDs ? explode(',', $ad->TaskIDs) : [];

                if ($ad->IdsType == 'WXTAG') {
                    $tmpWxIds = [];
                    // 全部微信
                    if ($ad->WxTagIDs == -1) {
                        $wxs = $wxModel->fetchAll($wxModel->select()->where('WeixinID in (?)', $onlineWxIds));
                        foreach ($wxs as $wx) {
                            $tmpWxIds[] = $wx->WeixinID;
                        }
                    } else {
                        foreach (explode(',', $ad->WxTagIDs) as $tagId) {
                            $wxs = $wxModel->fetchAll($wxModel->select()->where('find_in_set(?, CategoryIds)', $tagId)->where('WeixinID in (?)', $onlineWxIds));
                            foreach ($wxs as $wx) {
                                $tmpWxIds[] = $wx->WeixinID;
                            }
                        }
                    }
                    $wxIds = array_unique($tmpWxIds);

                    foreach ($wxIds as $wxId) {
                        $nextRunTimeUnix = time();
                        if ($ad->NextRunType == 'RAND') {
                            $nextRunTimeUnix += mt_rand(10, 3600);
                        }
                        $nextRunTime = date('Y-m-d H:i:s', $nextRunTimeUnix);
                        $taskId = $taskModel->addCommonTask(TASK_CODE_AD_CLICK, $wxId, json_encode($taskConfig), $ad->AdminID, $nextRunTime);
                        $oldTaskIds[] = $taskId;
                    }

                } else {
                    $deviceIds = explode(',', $ad->WxTagIDs);
                    foreach ($deviceIds as $deviceId) {
                        // 如果设备不在线, 则跳过
                        if (!in_array($deviceId, $onlineDeviceIds)) {
                            continue;
                        }
                        $nextRunTimeUnix = time();
                        if ($ad->NextRunType == 'RAND') {
                            $nextRunTimeUnix += mt_rand(10, 3600);
                        }
                        $nextRunTime = date('Y-m-d H:i:s', $nextRunTimeUnix);
                        $taskId = $taskModel->addDeviceTask(TASK_CODE_AD_CLICK, $deviceId, json_encode($taskConfig), $ad->AdminID, $nextRunTime);
                        $oldTaskIds[] = $taskId;
                    }
                }
                $oldTaskIds = array_unique($oldTaskIds);

                $tmpExecTime = json_decode($ad->ExecTime, 1);
                if (json_last_error() == JSON_ERROR_NONE) {
                    list($nextRunTime, $nextRunType) = Helper_Timer::getNextRunTime($ad->StartDate, $ad->EndDate, $tmpExecTime);
                } else {
                    // 兼容旧
                    list($nextRunTime, $nextRunType) = $adModel::getNextRunTime($ad->StartDate, $ad->EndDate, explode(',', $ad->ExecTime));
                }

                $ad->ExecutedNums += 1;
                $ad->NextRunTime = $nextRunTime;
                $ad->NextRunType = $nextRunType;
                $ad->LastRunTime = date('Y-m-d H:i:s');
                $ad->TaskIDs = implode(',', $oldTaskIds);
                if ($nextRunTime == '0000-00-00 00:00:00') {
                    $ad->Status = 'FINISH';
                }
                $ad->save();

                $adModel->getAdapter()->commit();
            } catch (\Exception $e) {
                $this->getLog()->add('err:' . $e->__toString());
                $adModel->getAdapter()->rollBack();
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
        self::getLog()->add('Found new release: ' . $this->getReleaseCheck()->getRelease() . ', will quit for update.');
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
