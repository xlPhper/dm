<?php
/**
 * socket check
 */
class TaskRun_SocketCheck extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='socketCheck';

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
        $dbConfig = $this->getDb()->getConfig();
        self::getLog()->add('db is ' . $dbConfig['host'] .';version:'.$this->getReleaseCheck()->getRelease());
        self::getLog()->flush();
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
