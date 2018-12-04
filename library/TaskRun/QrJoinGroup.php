<?php
/**
 * 加群任务
 */
class TaskRun_QrJoinGroup extends DM_Daemon
{
    const CRON_SLEEP = 3000000;
    const SERVICE='qrJoinGroup';

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
        try{
            $model = new Model_Group_QrJoin();
            $wxModel = new Model_Weixin();
            $gtModel = new Model_Group_Tmps();
            $task_model = new Model_Task();

            // 查询可执行的创建任务
            $data = $model->getData();
            foreach ($data as $d){
                // 获取标签下的所有微信号
                $wxs = $wxModel->findWeixinCategory($d['WeixinTags']);
                $TaskIDs = empty($d["TaskIDs"])?[]:explode(',', $d["TaskIDs"]);
                // 获取群信息
                $task_model->getAdapter()->beginTransaction();
                foreach($wxs as $wx){
                    $gts = $gtModel->getData($d['Channel'],"g",$d["JoinNum"]);
                    $timer = null;
                    $time = time();
                    if ($d["NextRunType"] == 'RAND') {
                        $time += mt_rand(10, 3600);
                    }
                    $timer = date('Y-m-d H:i:s', $time);
                    self::getLog()->add(json_encode($gts))->flush();
                    foreach ($gts as $g) {
                        $taskConfig = [
                            "JoinID" => $d['JoinID'],
                            "Code"   => [
                                $g["QRCodeImg"]
                            ]
                        ];
                        $gtModel->use($g["QrID"]);
                        $TaskIDs[] = $task_model::addCommonTask(TASK_CODE_GROUP_JOIN,$wx["WeixinID"], json_encode($taskConfig), $d["AdminID"],$timer);
                        $d["TotalNum"]++;
                    }
                }
                list($nextRunTime, $nextRunType) = Helper_Timer::getNextRunTime($d["StartDate"], $d["EndDate"], json_decode($d["ExecTime"],true));
                $updateData = [
                    "NextRunTime" => $nextRunTime,
                    "TotalNum"    => $d["TotalNum"],
                    "NextRunType" => $nextRunType,
                    "LastRunTime" => date('Y-m-d H:i:s'),
                    "TaskIDs"     => implode(",", $TaskIDs)
                ];
                if ($nextRunTime == '0000-00-00 00:00:00') {
                    $updateData["Status"] = 2;
                }
                // 记录最后的编号
                $model->update($updateData,['JoinID = ?'=>$d['JoinID']]);
                $task_model->getAdapter()->commit();
            }
        }catch(Exception $e){
            $task_model->getAdapter()->rollBack();
            self::getLog()->add('cron generate child task err:' . $e->__toString());
            self::getLog()->flush();
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
