<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/10/17
 * Time: 16:29
 * 开始抓取二维码任务
 */
class TaskRun_QrGatherStart extends DM_Daemon
{
    const CRON_SLEEP = 30000000;
    const SERVICE='qrGatherStart';

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
        set_time_limit(0);
        try{
            //初始化豆瓣搜索采集地址
            (new Model_Gather_Douban())->initGatherTask();
            die();
        } catch (Exception $e){
            self::getLog()->add($e->getMessage());
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