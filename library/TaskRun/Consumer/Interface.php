<?php

/**
 * schedule
 */
interface TaskRun_Consumer_Interface
{
    /**
     * 具体实现类
     */
    public function consumer($data);
}