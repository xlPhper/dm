<?php

/**
 * 具体实现类
 */
class TaskRun_Consumer_Test implements  TaskRun_Consumer_Interface
{
    /**
     * 具体实现方法
     */
    public function consumer($data)
    {
        DM_Log::create(TaskRun_Consumer::SERVICE)->add('data:' . json_encode($data));
    }
}