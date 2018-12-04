<?php

/**
 * redis 队列服务
 */
class Helper_DisQueue
{
    // ==================== job name (驼峰法命名)=====================
    const job_name_test = 'test';
    const job_name_msgSend = 'msgSend';
    // ==================== job name (驼峰法命名)=====================

    public static $instance = null;
    /**
     * @var Phloppy\Client\Producer
     */
    private $producer = null;
    /**
     * @var Phloppy\Client\Consumer
     */
    private $consumer = null;
    public static $job = null;

    const QK_QUEUE = 'qk_queue';

    public static function getInstance()
    {
        if (!self::$instance || !self::$instance instanceof self) {
            self::$instance = new self;
        }

        if (!self::$instance->producer) {
            self::$instance->producer = self::$instance->getProducer();
        }
        if (!self::$instance->consumer) {
            self::$instance->consumer = self::$instance->getConsumer();
        }

        return self::$instance;
    }

    /**
     * @return Phloppy\Client\Producer
     */
    public function getProducer()
    {
        if (is_null($this->producer)) {
            $config = Zend_Registry::get("config");
            $nodesConfig = $config['disQueue']['nodes'];

            $nodes = [];
            foreach ($nodesConfig as $node) {
                $ipPort = 'tcp://' . $node['host'] . ':' . $node['port'];
                $nodes[] = $ipPort;
            }


            if (empty($nodes)) {
                throw new \Exception('queue node config err');
            }

            try {
                $stream = new \Phloppy\Stream\CachingPool($nodes);
                $stream->connect();
                $this->producer = new \Phloppy\Client\Producer($stream);
            } catch (\Exception $e) {
                throw new \Exception('queue node disconnect, err:' . $e->getMessage());
            }
        }

        return $this->producer;
    }

    /**
     * @return Phloppy\Client\Consumer
     */
    public function getConsumer()
    {
        if (is_null($this->consumer)) {
            $config = Zend_Registry::get("config");
            $nodesConfig = $config['disQueue']['nodes'];

            $nodes = [];
            foreach ($nodesConfig as $node) {
                $ipPort = 'tcp://' . $node['host'] . ':' . $node['port'];
                $nodes[] = $ipPort;
            }


            if (empty($nodes)) {
                throw new \Exception('queue node config err');
            }

            try {
                $stream = new \Phloppy\Stream\CachingPool($nodes);
                $stream->connect();
                $this->consumer = new \Phloppy\Client\Consumer($stream);
            } catch (\Exception $e) {
                throw new \Exception('queue node disconnect, err:' . $e->getMessage());
            }
        }

        return $this->consumer;
    }

    /**
     * 入队列
     * @param $job string
     * @return string jobId
     */
    public function inQueue($jobName, $jobData, $delaySeconds = 0)
    {
        $inQueueData = [
            'name' => $jobName,
            'data' => $jobData
        ];
        $j =  \Phloppy\Job::create(['body' => json_encode($inQueueData)]);
        $j->setDelay($delaySeconds);
        $job = $this->producer->addJob(self::QK_QUEUE, $j);

        return $job->getId();
    }

}