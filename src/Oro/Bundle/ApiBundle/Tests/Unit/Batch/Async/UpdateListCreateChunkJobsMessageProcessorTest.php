<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Batch\Async;

use Oro\Bundle\ApiBundle\Batch\Async\AsyncOperationManager;
use Oro\Bundle\ApiBundle\Batch\Async\Topics;
use Oro\Bundle\ApiBundle\Batch\Async\UpdateListCreateChunkJobsMessageProcessor;
use Oro\Bundle\ApiBundle\Batch\Async\UpdateListProcessingHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\MessageQueueBundle\Entity\Job as JobEntity;
use Oro\Bundle\MessageQueueBundle\Entity\Repository\JobRepository;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Psr\Log\LoggerInterface;

class UpdateListCreateChunkJobsMessageProcessorTest extends \PHPUnit\Framework\TestCase
{
    private const BATCH_SIZE = 2000;

    /** @var \PHPUnit\Framework\MockObject\MockObject|JobRunner */
    private $jobRunner;

    /** @var \PHPUnit\Framework\MockObject\MockObject|JobRepository */
    private $jobRepository;

    /** @var \PHPUnit\Framework\MockObject\MockObject|AsyncOperationManager */
    private $operationManager;

    /** @var \PHPUnit\Framework\MockObject\MockObject|UpdateListProcessingHelper */
    private $processingHelper;

    /** @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface */
    private $logger;

    /** @var UpdateListCreateChunkJobsMessageProcessor */
    private $processor;

    protected function setUp(): void
    {
        $this->jobRunner = $this->createMock(JobRunner::class);
        $this->jobRepository = $this->createMock(JobRepository::class);
        $this->operationManager = $this->createMock(AsyncOperationManager::class);
        $this->processingHelper = $this->createMock(UpdateListProcessingHelper::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $doctrineHelper = $this->createMock(DoctrineHelper::class);
        $doctrineHelper->expects($this->any())
            ->method('getEntityRepository')
            ->with(JobEntity::class)
            ->willReturn($this->jobRepository);

        $this->processor = new UpdateListCreateChunkJobsMessageProcessor(
            $this->jobRunner,
            $doctrineHelper,
            $this->operationManager,
            $this->processingHelper,
            $this->logger
        );
    }

    /**
     * @param array $body
     * @param string $messageId
     *
     * @return MessageInterface
     */
    private function getMessage(array $body, string $messageId = '')
    {
        $message = $this->createMock(MessageInterface::class);
        $message->expects(self::once())
            ->method('getBody')
            ->willReturn(JSON::encode($body));
        $message->expects(self::any())
            ->method('getMessageId')
            ->willReturn($messageId);

        return $message;
    }

    /**
     * @return SessionInterface
     */
    private function getSession()
    {
        return $this->createMock(SessionInterface::class);
    }

    public function testGetSubscribedTopics()
    {
        self::assertEquals(
            [Topics::UPDATE_LIST_CREATE_CHUNK_JOBS],
            UpdateListCreateChunkJobsMessageProcessor::getSubscribedTopics()
        );
    }

    public function testShouldRejectInvalidMessage()
    {
        $message = $this->getMessage(['key' => 'value']);

        $this->logger->expects(self::once())
            ->method('critical')
            ->with('Got invalid message.');

        $result = $this->processor->process($message, $this->getSession());

        self::assertEquals(MessageProcessorInterface::REJECT, $result);
    }

    public function testShouldRejectNotExistingRootJobId()
    {
        $rootJobId = 100;
        $message = $this->getMessage([
            'operationId'          => 123,
            'entityClass'          => 'Test\Entity',
            'requestType'          => ['testRequest'],
            'version'              => '1.1',
            'rootJobId'            => $rootJobId,
            'chunkJobNameTemplate' => 'oro:batch_api:123:chunk:%s'
        ]);

        $this->jobRepository->expects(self::once())
            ->method('findJobById')
            ->with($rootJobId)
            ->willReturn(null);

        $this->logger->expects(self::once())
            ->method('critical')
            ->with('The root job does not exist.');

        $result = $this->processor->process($message, $this->getSession());

        self::assertEquals(MessageProcessorInterface::REJECT, $result);
    }

    public function testProcessNextIteration()
    {
        $operationId = 123;
        $rootJobId = 100;
        $chunkJobNameTemplate = 'oro:batch_api:123:chunk:%s';
        $chunkIndexCount = self::BATCH_SIZE + 1000;
        $nextChunkFileIndex = self::BATCH_SIZE;
        $aggregateTime = 200;
        $body = [
            'operationId'          => $operationId,
            'entityClass'          => 'Test\Entity',
            'requestType'          => ['testRequest'],
            'version'              => '1.1',
            'rootJobId'            => $rootJobId,
            'chunkJobNameTemplate' => $chunkJobNameTemplate
        ];
        $message = $this->getMessage($body);
        $rootJob = $this->createMock(Job::class);

        $this->jobRepository->expects(self::once())
            ->method('findJobById')
            ->with($rootJobId)
            ->willReturn($rootJob);
        $this->processingHelper->expects(self::once())
            ->method('getChunkIndexCount')
            ->with($operationId)
            ->willReturn($chunkIndexCount);
        $jobRunnerForChildJob = $this->createMock(JobRunner::class);
        $this->jobRunner->expects(self::once())
            ->method('getJobRunnerForChildJob')
            ->with(self::identicalTo($rootJob))
            ->willReturn($jobRunnerForChildJob);
        $this->processingHelper->expects(self::once())
            ->method('createChunkJobs')
            ->with(
                self::identicalTo($jobRunnerForChildJob),
                $operationId,
                $chunkJobNameTemplate,
                0,
                self::BATCH_SIZE - 1
            )
            ->willReturn($nextChunkFileIndex);
        $this->processingHelper->expects(self::once())
            ->method('calculateAggregateTime')
            ->with(self::isType('float'), 0)
            ->willReturn($aggregateTime);
        $this->processingHelper->expects(self::once())
            ->method('sendMessageToCreateChunkJobs')
            ->with(
                self::identicalTo($rootJob),
                $chunkJobNameTemplate,
                $body,
                $nextChunkFileIndex,
                $aggregateTime
            );

        $result = $this->processor->process($message, $this->getSession());

        self::assertEquals(MessageProcessorInterface::ACK, $result);
    }

    public function testProcessLastIteration()
    {
        $operationId = 123;
        $rootJobId = 100;
        $chunkJobNameTemplate = 'oro:batch_api:123:chunk:%s';
        $firstChunkFileIndex = 1000;
        $chunkIndexCount = self::BATCH_SIZE + 1000;
        $aggregateTime = 2345;
        $body = [
            'operationId'          => $operationId,
            'entityClass'          => 'Test\Entity',
            'requestType'          => ['testRequest'],
            'version'              => '1.1',
            'rootJobId'            => $rootJobId,
            'chunkJobNameTemplate' => $chunkJobNameTemplate,
            'firstChunkFileIndex'  => $firstChunkFileIndex,
            'aggregateTime'        => $aggregateTime
        ];
        $message = $this->getMessage($body);
        $rootJob = $this->createMock(Job::class);

        $this->jobRepository->expects(self::once())
            ->method('findJobById')
            ->with($rootJobId)
            ->willReturn($rootJob);
        $this->processingHelper->expects(self::once())
            ->method('getChunkIndexCount')
            ->with($operationId)
            ->willReturn($chunkIndexCount);
        $jobRunnerForChildJob = $this->createMock(JobRunner::class);
        $this->jobRunner->expects(self::once())
            ->method('getJobRunnerForChildJob')
            ->with(self::identicalTo($rootJob))
            ->willReturn($jobRunnerForChildJob);
        $this->processingHelper->expects(self::once())
            ->method('createChunkJobs')
            ->with(
                self::identicalTo($jobRunnerForChildJob),
                $operationId,
                $chunkJobNameTemplate,
                $firstChunkFileIndex,
                $chunkIndexCount - 1
            )
            ->willReturn($chunkIndexCount);
        $this->processingHelper->expects(self::once())
            ->method('sendMessageToStartChunkJobs')
            ->with(self::identicalTo($rootJob), $body);
        $this->processingHelper->expects(self::once())
            ->method('calculateAggregateTime')
            ->with(self::isType('float'), $aggregateTime)
            ->willReturn($aggregateTime + 100);
        $this->operationManager->expects(self::once())
            ->method('incrementAggregateTime')
            ->with($operationId, $aggregateTime + 100);

        $result = $this->processor->process($message, $this->getSession());

        self::assertEquals(MessageProcessorInterface::ACK, $result);
    }

    public function testProcessLastIterationWhenOnlyOneNotProcessedChunkRemains()
    {
        $operationId = 123;
        $rootJobId = 100;
        $chunkJobNameTemplate = 'oro:batch_api:123:chunk:%s';
        $firstChunkFileIndex = self::BATCH_SIZE;
        $chunkIndexCount = self::BATCH_SIZE + 1;
        $aggregateTime = 2345;
        $body = [
            'operationId'          => $operationId,
            'entityClass'          => 'Test\Entity',
            'requestType'          => ['testRequest'],
            'version'              => '1.1',
            'rootJobId'            => $rootJobId,
            'chunkJobNameTemplate' => $chunkJobNameTemplate,
            'firstChunkFileIndex'  => $firstChunkFileIndex,
            'aggregateTime'        => $aggregateTime
        ];
        $message = $this->getMessage($body);
        $rootJob = $this->createMock(Job::class);

        $this->jobRepository->expects(self::once())
            ->method('findJobById')
            ->with($rootJobId)
            ->willReturn($rootJob);
        $this->processingHelper->expects(self::once())
            ->method('getChunkIndexCount')
            ->with($operationId)
            ->willReturn($chunkIndexCount);
        $jobRunnerForChildJob = $this->createMock(JobRunner::class);
        $this->jobRunner->expects(self::once())
            ->method('getJobRunnerForChildJob')
            ->with(self::identicalTo($rootJob))
            ->willReturn($jobRunnerForChildJob);
        $this->processingHelper->expects(self::once())
            ->method('createChunkJobs')
            ->with(
                self::identicalTo($jobRunnerForChildJob),
                $operationId,
                $chunkJobNameTemplate,
                $firstChunkFileIndex,
                $chunkIndexCount - 1
            )
            ->willReturn($chunkIndexCount);
        $this->processingHelper->expects(self::once())
            ->method('sendMessageToStartChunkJobs')
            ->with(self::identicalTo($rootJob), $body);
        $this->processingHelper->expects(self::once())
            ->method('calculateAggregateTime')
            ->with(self::isType('float'), $aggregateTime)
            ->willReturn($aggregateTime + 100);
        $this->operationManager->expects(self::once())
            ->method('incrementAggregateTime')
            ->with($operationId, $aggregateTime + 100);

        $result = $this->processor->process($message, $this->getSession());

        self::assertEquals(MessageProcessorInterface::ACK, $result);
    }
}
