<?php

class TaskRun_ResourceSync extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='resourceSync';

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
            set_time_limit(0);
            $model = new Model_Open_Resource();
            $res = $model->select()->from($model->getTableName(),["Url","DepartmentID","Type"])
                ->where("DepartmentID > 0")->query()->fetchAll();
            $data = [];
            foreach ($res as $r) {
                $data[$r["DepartmentID"]][] = $r;
            }
            $adminModel = new Model_Role_Admin();
            foreach ($data as $DepartmentID => $d) {
                //todo 获取部门微信
                $wxs =  $adminModel->getDepartmentWx($DepartmentID);
                foreach ($wxs as $wx) {
                    Model_Task::addCommonTask(TASK_CODE_RESOURCE_SYNC,$wx["WeixinID"],json_encode($d));
                }
            }
            $this->getLog()->add("下发成功");
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage());
            self::getLog()->flush();
        }
        exit();
    }

    protected function init()
    {
        try{
            $redisconfig = Zend_Registry::get("config")['redis'];
            Helper_Redis::init($redisconfig);
            parent::init();
            self::getLog()->add("\n\n**********************定时更新**************************");
        } catch (Exception $e){
            self::getLog()->add("init error:".$e->getMessage());
        }
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