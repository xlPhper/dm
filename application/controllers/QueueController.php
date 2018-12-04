<?php

class QueueController extends DM_Controller
{
    /**
     * 队列消费
     */
    public function consumerAction()
    {
        TaskRun_Consumer::instance()->daemonRun();
    }

}

