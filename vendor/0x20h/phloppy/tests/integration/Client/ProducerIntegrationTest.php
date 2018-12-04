<?php

namespace Phloppy\Client;

use Phloppy\Job;

class ProducerIntegrationTest extends AbstractIntegrationTest {

    public function testAddJob()
    {
        $queue = 'test-'.substr(sha1(mt_rand()), 0, 6);
        $client= new Producer($this->stream);
        $job = new Job("foo");
        $this->assertEquals('', $job->getId());
        $client->addJob($queue, $job, true);
        $this->assertNotEquals('', $job->getId());
    }

    public function testAddJobAsync()
    {
        $queue = 'test-'.substr(sha1(mt_rand()), 0, 6);
        $producer = new Producer($this->stream);
        $consumer = new Consumer($this->stream);
        $this->assertEquals(
            $producer->addJob($queue, Job::create(['body' => __METHOD__]), 0, true)->getId(),
            $consumer->getJob($queue)->getId()
        );
    }

    public function testAddJobDelayed()
    {
        $queue = 'test-'.substr(sha1(mt_rand()), 0, 6);
        $producer = new Producer($this->stream);
        $consumer = new Consumer($this->stream);
        $job = new Job("foo");
        $job->setDelay(1);
        $job->setTtl(4);
        $job->setRetry(1);
        $this->assertEquals('', $job->getId());
        $producer->addJob($queue, $job);
        $this->assertNotEquals('', $job->getId());
        $this->assertNull($consumer->getJob($queue, 500));
        // now the job should be returned
        $j = $consumer->getJob($queue, 5000);
        $this->assertInstanceOf('\Phloppy\Job', $j);
    }

    /**
     * @expectedException \Phloppy\Exception\CommandException
     * @expectedExceptionMessage MAXLEN Queue is already longer than the specified MAXLEN count
     */
    public function testAddJobMaxlen()
    {
        $queue = 'test-'.substr(sha1(mt_rand()), 0, 6);
        $producer = new Producer($this->stream);
        $producer->addJob($queue, Job::create(['body' => 'job-maxlen-1']));
        $producer->addJob($queue, Job::create(['body' => 'job-maxlen-2']));
        $producer->addJob($queue, Job::create(['body' => 'job-maxlen-3']), 1);
    }
}
