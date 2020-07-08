<?php

namespace Oro\Component\MessageQueue\Tests\Unit\StatusCalculator;

use Oro\Component\MessageQueue\Checker\JobStatusChecker;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRepositoryInterface;
use Oro\Component\MessageQueue\StatusCalculator\QueryCalculator;
use Symfony\Bridge\Doctrine\ManagerRegistry;

class QueryCalculatorTest extends \PHPUnit\Framework\TestCase
{
    /** @var QueryCalculator */
    private $queryCalculator;

    /** @var JobRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $jobRepository;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->jobRepository = $this->createMock(JobRepositoryInterface::class);
        $entityClass = Job::class;
        $manager = $this->createMock(ManagerRegistry::class);
        $manager->expects($this->any())
            ->method('getRepository')
            ->with($entityClass)
            ->willReturn($this->jobRepository);
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->willReturn($manager);

        $jobStatusChecker = new JobStatusChecker();
        $this->queryCalculator = new QueryCalculator($doctrine, $entityClass);
        $this->queryCalculator->setJobStatusChecker($jobStatusChecker);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        unset($this->queryCalculator);
    }

    /**
     * @return array
     */
    public function calculateProgressProvider(): array
    {
        return [
            [
                [
                    Job::STATUS_NEW => 2,
                ],
                0,
            ],
            [
                [
                    Job::STATUS_RUNNING => 1,
                    Job::STATUS_NEW => 1,
                ],
                0,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_NEW => 1,
                ],
                0.5,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_RUNNING => 1,
                    Job::STATUS_NEW => 1,
                ],
                0.3333,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_FAILED => 1,
                    Job::STATUS_RUNNING => 1,
                ],
                0.6667,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 2,
                    Job::STATUS_FAILED => 1,
                ],
                1,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_FAILED => 1,
                    Job::STATUS_CANCELLED => 1,
                ],
                0.6667,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_STALE => 2,
                ],
                0.3333,
            ],
        ];
    }

    /**
     * @dataProvider calculateProgressProvider
     *
     * @param array $statuses
     * @param float $expectedStatusProgress
     */
    public function testCalculateRootJobProgress(array $statuses, float $expectedStatusProgress): void
    {
        $rootJob = new Job();
        $this->jobRepository
            ->expects($this->once())
            ->method('getChildStatusesWithJobCountByRootJob')
            ->with($rootJob)
            ->willReturn($statuses);

        $this->queryCalculator->init($rootJob);
        $statusProgress = $this->queryCalculator->calculateRootJobProgress();
        $this->assertEquals($expectedStatusProgress, $statusProgress);
    }

    /**
     * @return array
     */
    public function statusCalculateProvider(): array
    {
        return [
            [
                [
                    Job::STATUS_NEW => 2,
                ],
                Job::STATUS_NEW,
            ],
            [
                [
                    Job::STATUS_RUNNING => 1,
                    Job::STATUS_NEW => 1,
                ],
                Job::STATUS_RUNNING,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_NEW => 1,
                ],
                Job::STATUS_RUNNING,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_RUNNING => 1,
                    Job::STATUS_NEW => 1,
                ],
                Job::STATUS_RUNNING,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_FAILED => 1,
                    Job::STATUS_RUNNING => 1,
                ],
                Job::STATUS_RUNNING,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 2,
                    Job::STATUS_FAILED => 1,
                ],
                Job::STATUS_FAILED,
            ],
            [
                [
                    Job::STATUS_SUCCESS => 1,
                    Job::STATUS_FAILED => 1,
                    Job::STATUS_CANCELLED => 1,
                ],
                Job::STATUS_CANCELLED,
            ]
        ];
    }

    /**
     * @dataProvider statusCalculateProvider
     *
     * @param array $statuses
     * @param string $expectedStatus
     */
    public function testCalculateRootJobStatus(array $statuses, string $expectedStatus): void
    {
        $rootJob = new Job();
        $this->jobRepository
            ->expects($this->once())
            ->method('getChildStatusesWithJobCountByRootJob')
            ->with($rootJob)
            ->willReturn($statuses);

        $this->queryCalculator->init($rootJob);
        $status = $this->queryCalculator->calculateRootJobStatus();
        $this->assertEquals($expectedStatus, $status);
    }
}
