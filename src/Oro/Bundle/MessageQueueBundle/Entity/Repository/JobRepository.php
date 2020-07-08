<?php

namespace Oro\Bundle\MessageQueueBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Oro\Bundle\MessageQueueBundle\Entity\Job as JobEntity;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRepositoryInterface;

/**
 * Job entity repository.
 */
class JobRepository extends EntityRepository implements JobRepositoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @return JobEntity|null
     */
    public function findJobById(int $id): ?Job
    {
        $qb = $this->createQueryBuilder('job');
        $qb
            ->select(['job', 'rootJob'])
            ->leftJoin('job.rootJob', 'rootJob')
            ->where($qb->expr()->eq('job', ':id'))
            ->setParameter('id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     *
     * @return JobEntity|null
     */
    public function findRootJobByOwnerIdAndJobName(string $ownerId, string $jobName): ?Job
    {
        $qb = $this->createQueryBuilder('job');
        $qb
            ->where($qb->expr()->isNull('job.rootJob'))
            ->andWhere($qb->expr()->eq('job.name', ':jobName'))
            ->andWhere($qb->expr()->eq('job.ownerId', ':ownerId'))
            ->setParameter('ownerId', $ownerId)
            ->setParameter('jobName', $jobName);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     *
     * @return JobEntity|null
     */
    public function findRootJobByJobNameAndStatuses(string $jobName, array $statuses): ?Job
    {
        $qb = $this->createQueryBuilder('job');
        $qb
            ->where($qb->expr()->isNull('job.rootJob'))
            ->andWhere($qb->expr()->eq('job.name', ':jobName'))
            ->andWhere($qb->expr()->in('job.status', ':statuses'))
            ->andWhere($qb->expr()->neq('job.interrupted', ':interrupted'))
            ->setParameter('jobName', $jobName)
            ->setParameter('statuses', $statuses)
            ->setParameter('interrupted', true)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     *
     * @return JobEntity|null
     */
    public function findChildJobByName(string $name, Job $rootJob): ?Job
    {
        $qb = $this->createQueryBuilder('job');
        $qb
            ->select(['job', 'rootJob'])
            ->leftJoin('job.rootJob', 'rootJob')
            ->where($qb->expr()->eq('job.rootJob', ':rootJob'))
            ->andWhere($qb->expr()->eq('job.name', ':name'))
            ->setParameter('rootJob', $rootJob)
            ->setParameter('name', $name);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getChildStatusesWithJobCountByRootJob(Job $rootJob): array
    {
        $qb = $this->createQueryBuilder('job');
        $rawChildStatusesWithJobCount = $qb
            ->select('COUNT(job.id) AS childCount', 'job.status')
            ->where($qb->expr()->eq('job.rootJob', ':rootJob'))
            ->groupBy('job.status')
            ->setParameter('rootJob', $rootJob)
            ->getQuery()
            ->getScalarResult();

        $childStatusesWithJobCount = [];
        foreach ($rawChildStatusesWithJobCount as $childStatusWithJobCount) {
            $childStatusesWithJobCount[$childStatusWithJobCount['status']] = $childStatusWithJobCount['childCount'];
        }

        return $childStatusesWithJobCount;
    }

    /**
     * {@inheritdoc}
     */
    public function getChildJobIdsByRootJobAndStatus(Job $rootJob, string $status): array
    {
        $qb = $this->createQueryBuilder('job');
        $rawChildJobIds = $qb
            ->select('job.id')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('job.rootJob', ':rootJob'),
                    $qb->expr()->eq('job.status', ':status')
                )
            )
            ->setParameters([
                'rootJob' => $rootJob,
                'status' => $status,
            ])
            ->getQuery()
            ->getScalarResult();

        return array_column($rawChildJobIds, 'id');
    }

    /**
     * {@inheritdoc}
     *
     * @return JobEntity
     */
    public function createJob(): Job
    {
        return new $this->_entityName();
    }
}
