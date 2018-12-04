<?php

/**
 * schedule
 */
class TaskRun_Schedule extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE = 'schedule';
    /**
     * @var Model_Task
     */
    protected $taskModel = null;

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
        // select * from schedules where NextRunTime > LastRunTime and CURRENT_TIMESTAMP() >= NextRunTime and Status = 'PROCESS'
        $sModel = new Model_Schedules();
        $this->taskModel = new Model_Task();
        $select = $sModel->select()
            ->where('NextRunTime > LastRunTime')
            ->where('CURRENT_TIMESTAMP() >= NextRunTime')
            ->where('Status = ?', 'PROCESS')
            ->where('DeleteTime = ?', '0000-00-00 00:00:00');
        $schedules = $sModel->fetchAll($select);
        foreach ($schedules as $s) {
            $scheConfigs = json_decode($s->ScheConfigs, 1);
            try {
                $sModel->getAdapter()->beginTransaction();
                $runMateId = 0;
                $runWxIdType = 0;
                $runWxIds = '';
                $runK = '';
                $execType = '';
                foreach ($scheConfigs as $k => $config) {
                    // 兼容原来没有 ExecType 此键, 没有则认为是指定
                    if (isset($config['ExecType']) && $config['ExecType'] == 'RAND') {
                        $configExecTimeUnix = strtotime($config['ExecTime'].':00');
                    } else {
                        $configExecTimeUnix = strtotime($config['ExecTime']);
                    }
                    $nextRunTimeUnix = strtotime($s->NextRunTime);
                    $unixDiff = $nextRunTimeUnix - $configExecTimeUnix;
                    // 如果误差在50s内, 则认为相等
                    if ($unixDiff >= 0 && $unixDiff <= 50) {
                        $runMateId = $config['MateID'];
                        $runWxIdType = $s->WxIdType;
                        $runWxIds = $s->WeixinIDs;
                        $runK = $k;
                        $execType = isset($config['ExecType']) && $config['ExecType'] == 'RAND' ? 'RAND' : 'REFER';
                        break;
                    }
                }
                if ($runMateId > 0) {
                    $taskIds = $this->addSendWxGroupTask($s->ScheduleID, $runMateId, $runWxIdType, $runWxIds, $s->AdminID, $execType);

                    $oldTaskIds = $s->TaskIDs ? explode(',', $s->TaskIDs) : [];
                    $newTaskIds = array_merge($oldTaskIds, $taskIds);

                    $s->ExecutedNums += 1;
                    if ($runK == count($scheConfigs) - 1) {
                        $s->NextRunTime = '0000-00-00 00:00:00';
                    } else {
                        $s->NextRunTime = date('Y-m-d H:i:s', strtotime($scheConfigs[$runK + 1]['ExecTime']));
                    }
                    if ($s->NextRunTime == '0000-00-00 00:00:00') {
                        $s->Status = 'FINISH';
                    }
                    $s->LastRunTime = date('Y-m-d H:i:s');
                    $s->TaskIDs = implode(',', $newTaskIds);
                    // 下次
                    $s->save();
                }

                $sModel->getAdapter()->commit();
            } catch (\Exception $e) {
                $this->getLog()->add('err:'.$e->__toString());
                $sModel->getAdapter()->rollBack();
            }
        }
    }

    /**
     * 增加发朋友圈任务
     * @return array 任务ids
     */
    protected function addSendWxGroupTask($scheduleId, $mateId, $wxIdType, $wxIds, $adminId = 0, $execType = 'REFER')
    {
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();
        if (empty($onlineWxIds)) {
            return [];
        }


        $wxIds = explode(',', $wxIds);
        $wxIds = array_unique($wxIds);
        $wxModel = new Model_Weixin();
        if ($wxIdType == 'WX_TAG') {
            // 当是分类的时候, 查询分类下所有微信id
            $tmpWxIds = [];
            foreach ($wxIds as $tagId) {
                $wxs = $wxModel->fetchAll($wxModel->select()->where('find_in_set(?, CategoryIds)', $tagId)->where('WeixinID in (?)', $onlineWxIds));
                foreach ($wxs as $wx) {
                    $tmpWxIds[] = $wx->WeixinID;
                }
            }
            $wxIds = array_unique($tmpWxIds);
        } else {
            $tmpWxIds = [];
            foreach ($wxIds as $wxId) {
                if (in_array($wxId, $onlineWxIds)) {
                    $tmpWxIds[] = $wxId;
                }
            }
            $wxIds = array_unique($tmpWxIds);
//            $wxIds = array_intersect($wxIds, $onlineWxIds);
        }
        if (empty($wxIds)) {
            $this->getLog()->add('排期id:'.$scheduleId.'没有找到在线微信');
            return [];
        }

        // 获取 task config
        $mate = (new Model_Materials())->fetchRow(['MaterialID = ?' => $mateId]);
        if (!$mate) {
            return [];
        }
        $mate = $mate->toArray();

        // 处理
        if ($mate['Type'] == 2) {
            $titles = [
                'title' => $mate['ProductTitle'],
                'new-price' => $mate['SalePrice'],
                'qrcode' => $mate['ProductLink']
            ];
            $mate['MediaContent'] = $this->dealProductImg($mate['MediaContent'], $mate['StyleID'], $titles, $mate['UseStyleIndexs']);
        }
        $taskConfig = [
            'ScheduleID' => $scheduleId,
            'MaterialID' => $mateId,
            'TextContent' => $mate['TextContent'],
            'Comments' => $mate['Comments'] ? json_decode($mate['Comments'], 1) : [],
            'MediaType' => (int)$mate['MediaType'],
            'Position' => $mate['Position'],
            'Address' => $mate['Address'],
            'AddressCustom' => $mate['AddressCustom'],
            'AddressID' => $mate['AddressID'],
            'AddressName' => $mate['AddressName'],
            'MediaContent' => $mate['MediaContent']
        ];

        // 循环写入 task
        $taskIds = [];
        foreach ($wxIds as $wxId) {
            $nextRunTimeUnix = time();
            if ($execType == 'RAND') {
                $nextRunTimeUnix += mt_rand(10, 3600);
            }
            $nextRunTime = date('Y-m-d H:i:s', $nextRunTimeUnix);
            $taskIds[] = $this->taskModel->addCommonTask(TASK_CODE_WEIXIN_GROUP, $wxId, json_encode($taskConfig), $adminId, $nextRunTime);
        }
        return $taskIds;
    }

    /**
     * 处理商品图片
     */
    protected function dealProductImg($images, $styleId, $titles, $useStyleIndexs = '')
    {
        if ($styleId < 1) {
            return $images;
        }
        $style = (new Model_Styles())->fetchRow(['StyleID = ?' => $styleId]);
        if (!$style) {
            return $images;
        }
        $styleConfig = json_decode($style->Config, 1);

        $useStyleIndexs = explode(',', $useStyleIndexs);
        $images = explode(',', $images);
        $tmpImgs = [];
        foreach ($images as $index => $img) {
            if (in_array($index, $useStyleIndexs)) {
                $imResource = Model_Styles::initStyle($styleConfig, $titles)->buildImg($img);
                if (false === $imResource) {
                    $tmpImgs[] = $img;
                    continue;
                }
                $imgName = md5($img);
                ob_start();
                imagejpeg($imResource);
                $contents = ob_get_contents();
                ob_end_clean();
                $tmpImgs[] = DM_Qiniu::uploadBinary($imgName, $contents);
                // 释放内存
                imagedestroy($imResource);
            } else {
                $tmpImgs[] = $img;
            }
        }

        return implode(',', $tmpImgs);
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
