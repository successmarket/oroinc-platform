<?php

namespace Oro\Component\MessageQueue\Test\Async;

use Oro\Component\MessageQueue\Client\Config;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\DependentJobService;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Depended message processor for test purposes.
 */
class DependentMessageProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    private const TEST_TOPIC = 'oro.message_queue.test_topic';
    private const TEST_DEPENDENT_JOB_TOPIC = 'oro.message_queue.dependent_test_topic';
    public const TEST_JOB_NAME = 'test_job_dependent|123456789';

    /** @var JobRunner */
    private $jobRunner;

    /** @var DependentJobService */
    private $dependentJobService;

    /**
     * @param JobRunner $jobRunner
     * @param DependentJobService $dependentJobService
     */
    public function __construct(JobRunner $jobRunner, DependentJobService $dependentJobService)
    {
        $this->jobRunner = $jobRunner;
        $this->dependentJobService = $dependentJobService;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        if ($message->getProperty(Config::PARAMETER_TOPIC_NAME) === self::TEST_DEPENDENT_JOB_TOPIC) {
            return self::ACK;
        }

        $ownerId = $message->getMessageId();

        return $this->runUnique($ownerId) ? self::ACK : self::REJECT;
    }

    /**
     * @param string $ownerId
     *
     * @return bool
     */
    private function runUnique($ownerId): bool
    {
        $jobName = self::TEST_JOB_NAME;
        $closure = function (JobRunner $jobRunner, Job $job) {
            $context = $this->dependentJobService->createDependentJobContext($job->getRootJob());
            $context->addDependentJob(
                self::TEST_DEPENDENT_JOB_TOPIC,
                [
                    'rootJobId' => $job->getRootJob()->getId(),
                ]
            );
            $this->dependentJobService->saveDependentJob($context);

            return true;
        };

        return $this->jobRunner->runUnique($ownerId, $jobName, $closure);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics(): array
    {
        return [self::TEST_TOPIC, self::TEST_DEPENDENT_JOB_TOPIC];
    }
}
