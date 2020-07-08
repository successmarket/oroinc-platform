<?php

namespace Oro\Component\MessageQueue\Test\Async;

use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;

/**
 * Unique message processor for test purposes.
 */
class UniqueMessageProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    private const TEST_TOPIC = 'oro.message_queue.unique_test_topic';
    public const TEST_JOB_NAME = 'test_job_unique|123456789';

    /** @var JobRunner */
    private $jobRunner;

    /**
     * @param JobRunner $jobRunner
     */
    public function __construct(JobRunner $jobRunner)
    {
        $this->jobRunner = $jobRunner;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        $messageBody = JSON::decode($message->getBody());

        if (false === is_array($messageBody)) {
            return self::REJECT;
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
        $closure = function () {
            return true;
        };

        return $this->jobRunner->runUnique($ownerId, $jobName, $closure);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics(): array
    {
        return [self::TEST_TOPIC];
    }
}
