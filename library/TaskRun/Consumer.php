<?php

/**
 * schedule
 */
class TaskRun_Consumer extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE = 'consumer';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $consumer = Helper_DisQueue::getInstance()->getConsumer();
        while (true) {
            $job = $consumer->getJob(Helper_DisQueue::QK_QUEUE);
            if ($job) {
                $consumer->ack($job);
                $data = $job->getBody();
                $data = json_decode($data, 1);
                $jobName = $data['name'];
                $jobData = $data['data'];
                $className = 'TaskRun_Consumer_' . ucfirst($jobName);
                $this->consumerJob($className, $jobData);
            }
            if (false === $this->getReleaseCheck()->check()) {
                // 等待10s后重启新版本, 防止子进程没有处理完任务
                sleep(10);
                $this->onNewReleaseFind();
            }
        }
    }

    /**
     * 消费任务(无阻塞子进程处理)
     */
    protected function consumerJob($className, $jobData)
    {
        if (!class_exists($className)) {
            return;
        }

        if (!function_exists("pcntl_fork")) {
            (new $className())->consumer($jobData);
        } else {
//            pcntl_signal(SIGCHLD, 'sig_func');
            $pid = pcntl_fork();  //创建子进程
            //父进程和子进程都会执行下面代码
            if ($pid == -1) {
                //错误处理：创建子进程失败时返回-1.
                (new $className())->consumer($jobData);
            } else if ($pid) {
                //父进程会得到子进程号，所以这里是父进程执行的逻辑
                //如果不需要阻塞进程，而又想得到子进程的退出状态，则可以注释掉pcntl_wait($status)语句，或写成：
                DM_Log::create(TaskRun_Consumer::SERVICE)->add('pid:' . $pid);
                DM_Log::create(TaskRun_Consumer::SERVICE)->flush();
                pcntl_wait($status, WNOHANG); //等待子进程中断，防止子进程成为僵尸进程。
            } else {
                //子进程得到的$pid为0, 所以这里是子进程执行的逻辑。
                (new $className())->consumer($jobData);
                exit(0);
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