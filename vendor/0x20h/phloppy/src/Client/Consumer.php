<?php
namespace Phloppy\Client;

use Phloppy\Exception\CommandException;
use Phloppy\Job;
use Phloppy\RespUtils;

/**
 * Consumer client implementation.
 */
class Consumer extends AbstractClient {

    /**
     * @param string|string[] $queues
     * @param int $count
     * @param int $timeoutMs
     * @return Job[]
     */
    public function getJobs($queues, $count = 1, $timeoutMs = 200)
    {
        return $this->mapJobs((array) $this->send(array_merge(
            [
                'GETJOB',
                'TIMEOUT',
                $timeoutMs,
                'COUNT',
                (int) $count,
                'FROM',
            ],
            (array) $queues
        )));
    }


    /**
     * Retrieve a single job from the given queues
     *
     * @param string|string[] $queues
     * @param int $timeoutMs How long to block client when waiting for new jobs.
     * @return Job|null A Job if found, null otherwise.
     */
    public function getJob($queues, $timeoutMs = 200) {
        $jobs = $this->getJobs($queues, 1, $timeoutMs);

        if (empty($jobs)) {
            return null;
        }

        return $jobs[0];
    }


    /**
     * Acknowledge a job execution.
     *
     * @param Job $job
     *
     * @return int Number of Jobs acknowledged.
     */
    public function ack(Job $job)
    {
        assert($job->getId() != null);
        return (int) $this->send(['ACKJOB', $job->getId()]);
    }


    /**
     * Fast Acknowledge a job execution.
     *
     * @param Job $job
     *
     * @return int Number of Jobs acknowledged.
     * @see https://github.com/antirez/disque#fast-acknowledges
     */
    public function fastAck(Job $job)
    {
        assert($job->getId() != null);
        return (int) $this->send(['FASTACK', $job->getId()]);
    }


    /**
     * Return the job with the given jobId.
     *
     * @param string $jobId
     *
     * @return Job|null
     * @throws CommandException
     */
    public function show($jobId)
    {
        $result = $this->send(['SHOW', (string) $jobId]);

        if (!$result) {
            return null;
        }

        return Job::create(RespUtils::toAssoc($result));
    }


    /**
     * NACK the given job ids.
     *
     * @param string[] $jobIds
     * @return int Number of NACK'd jobs
     */
    public function nack(array $jobIds)
    {
        return (int) $this->send(array_merge(['NACK'], $jobIds));
    }


    /**
     * Send a notice to the server that this client is still processing the job.
     *
     * @param Job $job
     *
     * @return int The current retry time for the job
     *
     * @see https://github.com/antirez/disque#working-jobid
     */
    public function working(Job $job)
    {
        return (int) $this->send(['WORKING', $job->getId()]);
    }
}
