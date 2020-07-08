<?php

namespace Oro\Bundle\DataAuditBundle\EventListener;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\DataAuditBundle\Async\Topics;
use Oro\Bundle\DataAuditBundle\Model\AdditionalEntityChangesToAuditStorage;
use Oro\Bundle\DataAuditBundle\Provider\AuditConfigProvider;
use Oro\Bundle\DataAuditBundle\Provider\AuditMessageBodyProvider;
use Oro\Bundle\DataAuditBundle\Service\EntityToEntityChangeArrayConverter;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\PlatformBundle\EventListener\OptionalListenerInterface;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The listener does not support next features:
 *
 * Collection::clear - the deletion diff is empty because clear does takeSnapshot internally
 * Collection::removeElement - in case of "fetch extra lazy" does not schedule anything
 * "Doctrine will only check the owning side of an association for changes."
 * http://doctrine-orm.readthedocs.io/projects/doctrine-orm/en/latest/reference/unitofwork-associations.html
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class SendChangedEntitiesToMessageQueueListener implements OptionalListenerInterface
{
    private const BATCH_SIZE = 100;

    /** @var MessageProducerInterface */
    private $messageProducer;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var EntityToEntityChangeArrayConverter */
    private $entityToArrayConverter;

    /** @var AuditConfigProvider */
    private $auditConfigProvider;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $enabled = true;

    /** @var \SplObjectStorage */
    private $allInsertions;

    /** @var \SplObjectStorage */
    private $allUpdates;

    /** @var \SplObjectStorage */
    private $allDeletions;

    /** @var \SplObjectStorage */
    private $allCollectionUpdates;

    /** @var \SplObjectStorage */
    private $allTokens;

    /** @var AdditionalEntityChangesToAuditStorage */
    private $additionalEntityChangesStorage;

    /** @var AuditMessageBodyProvider */
    private $auditMessageBodyProvider;

    /** @var EntityNameResolver */
    private $entityNameResolver;

    /** @var PropertyAccessor */
    private $propertyAccessor;

    /**
     * @param MessageProducerInterface $messageProducer
     * @param TokenStorageInterface $tokenStorage
     * @param AdditionalEntityChangesToAuditStorage $additionalEntityChangesStorage
     * @param EntityToEntityChangeArrayConverter $entityToArrayConverter
     * @param AuditConfigProvider $auditConfigProvider
     * @param LoggerInterface $logger
     * @param AuditMessageBodyProvider $auditMessageBodyProvider
     */
    public function __construct(
        MessageProducerInterface $messageProducer,
        TokenStorageInterface $tokenStorage,
        AdditionalEntityChangesToAuditStorage $additionalEntityChangesStorage,
        EntityToEntityChangeArrayConverter $entityToArrayConverter,
        AuditConfigProvider $auditConfigProvider,
        LoggerInterface $logger,
        AuditMessageBodyProvider $auditMessageBodyProvider
    ) {
        $this->messageProducer = $messageProducer;
        $this->tokenStorage = $tokenStorage;
        $this->additionalEntityChangesStorage = $additionalEntityChangesStorage;
        $this->entityToArrayConverter = $entityToArrayConverter;
        $this->auditConfigProvider = $auditConfigProvider;
        $this->logger = $logger;
        $this->auditMessageBodyProvider = $auditMessageBodyProvider;

        $this->allInsertions = new \SplObjectStorage;
        $this->allUpdates = new \SplObjectStorage;
        $this->allDeletions = new \SplObjectStorage;
        $this->allCollectionUpdates = new \SplObjectStorage;
        $this->allTokens = new \SplObjectStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnabled($enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        if (!$this->enabled) {
            return;
        }

        $em = $eventArgs->getEntityManager();

        $this->findAuditableInsertions($em);
        $this->findAuditableUpdates($em);
        $this->findAuditableDeletions($em);
        $this->findAuditableCollectionUpdates($em);

        $token = $this->tokenStorage->getToken();
        if (null !== $token) {
            $this->allTokens[$em] = $token;
        }
    }

    /**
     * @param PostFlushEventArgs $eventArgs
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        if (!$this->enabled) {
            return;
        }

        $em = $eventArgs->getEntityManager();
        try {
            $insertions = $this->processInsertions($em);
            $updates = $this->processUpdates($em);
            $deletes = $this->processDeletions($em);
            $collectionUpdates = $this->processCollectionUpdates($em, $insertions, $updates, $deletes);

            do {
                $body = $this->auditMessageBodyProvider->prepareMessageBody(
                    array_splice($insertions, 0, self::BATCH_SIZE),
                    array_splice($updates, 0, self::BATCH_SIZE),
                    array_splice($deletes, 0, self::BATCH_SIZE),
                    array_splice($collectionUpdates, 0, self::BATCH_SIZE),
                    $this->getSecurityToken($em)
                );

                if (!empty($body)) {
                    $this->messageProducer->send(
                        Topics::ENTITIES_CHANGED,
                        new Message($body, MessagePriority::VERY_LOW)
                    );
                }
            } while ($body);
        } finally {
            $this->allInsertions->detach($em);
            $this->allUpdates->detach($em);
            $this->allDeletions->detach($em);
            $this->allCollectionUpdates->detach($em);
            $this->allTokens->detach($em);
        }
    }

    /**
     * @param EntityManager $em
     *
     * @return TokenInterface|null
     */
    private function getSecurityToken(EntityManager $em)
    {
        return $this->allTokens->contains($em)
            ? $this->allTokens[$em]
            : $this->tokenStorage->getToken();
    }

    /**
     * @param EntityManager $em
     */
    private function findAuditableInsertions(EntityManager $em)
    {
        $uow = $em->getUnitOfWork();

        $insertions = new \SplObjectStorage();
        $scheduledInsertions = $uow->getScheduledEntityInsertions();
        foreach ($scheduledInsertions as $entity) {
            if (!$this->auditConfigProvider->isAuditableEntity(ClassUtils::getClass($entity))) {
                continue;
            }

            $insertions[$entity] = $uow->getEntityChangeSet($entity);
        }

        $this->saveChanges($this->allInsertions, $em, $insertions);
    }

    /**
     * @param EntityManager $em
     */
    private function findAuditableUpdates(EntityManager $em)
    {
        $uow = $em->getUnitOfWork();

        $updates = new \SplObjectStorage();
        $scheduledUpdates = $uow->getScheduledEntityUpdates();
        foreach ($scheduledUpdates as $entity) {
            if (!$this->auditConfigProvider->isAuditableEntity(ClassUtils::getClass($entity))) {
                continue;
            }

            $updates[$entity] = $uow->getEntityChangeSet($entity);
        }

        $this->saveChanges($this->allUpdates, $em, $updates);
    }

    /**
     * @param EntityManager $em
     */
    private function findAuditableDeletions(EntityManager $em)
    {
        $uow = $em->getUnitOfWork();

        $deletions = new \SplObjectStorage();
        $scheduledDeletions = $uow->getScheduledEntityDeletions();
        foreach ($scheduledDeletions as $entity) {
            $entityClass = ClassUtils::getClass($entity);
            if (!$this->auditConfigProvider->isAuditableEntity($entityClass)) {
                continue;
            }

            $changeSet = [];
            $fields = $this->auditConfigProvider->getAuditableFields($entityClass);
            $classMetadata = $em->getClassMetadata($entityClass);
            $fields[] = $classMetadata->getSingleIdentifierFieldName();
            $originalData = $uow->getOriginalEntityData($entity);
            foreach ($fields as $field) {
                try {
                    $oldValue = $this->propertyAccessor->getValue($entity, $field) ?? $originalData[$field] ?? null;
                    $changeSet[$field] = [$oldValue, null];
                } catch (NoSuchPropertyException $e) {
                }
            }

            $entityName = $this->entityNameResolver->getName($entity);
            $deletion = $this->convertEntityToArray($em, $entity, $changeSet, $entityName);

            $deletions[$entity] = $deletion;

            if (null === $deletion['entity_id']) {
                $this->logger->error(
                    sprintf('The entity "%s" has an empty id.', $deletion['entity_class']),
                    ['entity' => $entity, 'deletion' => $deletion]
                );
            }
        }

        $this->saveChanges($this->allDeletions, $em, $deletions);
    }

    /**
     * @param EntityManager $em
     */
    private function findAuditableCollectionUpdates(EntityManager $em)
    {
        $uow = $em->getUnitOfWork();

        $collectionUpdates = new \SplObjectStorage();

        /** @var PersistentCollection[] $scheduledCollectionDeletions */
        $scheduledCollectionDeletions = $uow->getScheduledCollectionDeletions();
        foreach ($scheduledCollectionDeletions as $deleteCollection) {
            if (!$this->auditConfigProvider->isAuditableEntity($deleteCollection->getTypeClass()->getName())) {
                continue;
            }

            $collectionUpdates[$deleteCollection] = [
                'insertDiff' => [],
                'deleteDiff' => $deleteCollection->toArray(),
                'changeDiff' => [],
            ];
        }

        /** @var PersistentCollection[] $scheduledCollectionUpdates */
        $scheduledCollectionUpdates = $uow->getScheduledCollectionUpdates();
        foreach ($scheduledCollectionUpdates as $updateCollection) {
            if (!$this->auditConfigProvider->isAuditableEntity($updateCollection->getTypeClass()->getName())) {
                continue;
            }

            $collectionUpdates[$updateCollection] = [
                'insertDiff' => $updateCollection->getInsertDiff(),
                'deleteDiff' => $updateCollection->getDeleteDiff(),
                'changeDiff' => array_filter(
                    $updateCollection->toArray(),
                    function ($entity) use ($uow, $updateCollection) {
                        return $uow->isScheduledForUpdate($entity) &&
                            !in_array($entity, $updateCollection->getInsertDiff(), true) &&
                            !in_array($entity, $updateCollection->getDeleteDiff(), true);
                    }
                ),
            ];
        }

        $this->saveChanges($this->allCollectionUpdates, $em, $collectionUpdates);
    }

    /**
     * @param \SplObjectStorage $storage
     * @param EntityManager     $em
     * @param \SplObjectStorage $changes
     */
    private function saveChanges(\SplObjectStorage $storage, EntityManager $em, \SplObjectStorage $changes)
    {
        if ($changes->count() > 0) {
            if (!$storage->contains($em)) {
                $storage[$em] = $changes;
            } else {
                $previousChangesInCurrentTransaction = $storage[$em];
                $changes->addAll($previousChangesInCurrentTransaction);
                $storage[$em] = $changes;
            }
        }
    }

    /**
     * @param EntityManager $em
     *
     * @return array
     */
    private function processInsertions(EntityManager $em)
    {
        if (!$this->allInsertions->contains($em)) {
            return [];
        }

        $insertions = [];
        foreach ($this->allInsertions[$em] as $entity) {
            $changeSet = $this->allInsertions[$em][$entity];
            $insertions[$this->getEntityHash($entity)] = $this->convertEntityToArray($em, $entity, $changeSet);
        }

        return $insertions;
    }

    /**
     * @param EntityManager $em
     *
     * @return array
     */
    private function processUpdates(EntityManager $em)
    {
        $updates = $this->getUpdates($em);

        if (!$this->additionalEntityChangesStorage->hasEntityUpdates($em)) {
            return $updates;
        }

        $additionalUpdates = $this->additionalEntityChangesStorage->getEntityUpdates($em);
        foreach ($additionalUpdates as $entity) {
            $changeSet = $additionalUpdates->offsetGet($entity);
            $additionalUpdate = $this->processUpdate($em, $entity, $changeSet);
            if (!$additionalUpdate) {
                continue;
            }

            $key = spl_object_hash($entity);
            if (array_key_exists($key, $updates)) {
                $updates[$key]['change_set'] = array_merge(
                    $updates[$key]['change_set'],
                    $additionalUpdate['change_set']
                );
            } else {
                $updates[$this->getEntityHash($entity)] = $additionalUpdate;
            }
        }
        $this->additionalEntityChangesStorage->clear($em);

        return $updates;
    }

    /**
     * @param EntityManager $em
     *
     * @return array
     */
    private function getUpdates(EntityManager $em): array
    {
        $updates = [];
        if ($this->allUpdates->contains($em)) {
            foreach ($this->allUpdates[$em] as $entity) {
                $changeSet = $this->allUpdates[$em][$entity];
                $update = $this->processUpdate($em, $entity, $changeSet);
                if (!$update) {
                    continue;
                }

                $updates[$this->getEntityHash($entity)] = $update;
            }
        }

        return $updates;
    }

    /**
     * @param EntityManager $entityManager
     * @param object        $entity
     * @param array         $changeSet
     *
     * @return array|null
     */
    private function processUpdate(EntityManager $entityManager, $entity, array $changeSet)
    {
        $update = $this->convertEntityToArray($entityManager, $entity, $changeSet);
        if (null !== $update['entity_id']) {
            return $update;
        }

        $this->logger->error(
            sprintf('The entity "%s" has an empty id.', $update['entity_class']),
            ['entity' => $entity, 'update' => $update]
        );

        return null;
    }

    /**
     * @param EntityManager $em
     *
     * @return array
     */
    private function processDeletions(EntityManager $em)
    {
        if (!$this->allDeletions->contains($em)) {
            return [];
        }

        $deletions = [];
        foreach ($this->allDeletions[$em] as $entity) {
            $deletions[$this->getEntityHash($entity)] = $this->allDeletions[$em][$entity];
        }

        return $deletions;
    }

    /**
     * @param EntityManager $em
     * @param array $insertions
     * @param array $updates
     * @param array $deletions
     * @return array
     */
    private function processCollectionUpdates(
        EntityManager $em,
        array $insertions = [],
        array $updates = [],
        array $deletions = []
    ) {
        if (!$this->allCollectionUpdates->contains($em)) {
            return [];
        }

        $collectionUpdates = [];
        /** @var PersistentCollection $collection */
        foreach ($this->allCollectionUpdates[$em] as $collection) {
            $inserted = [];
            $deleted = [];
            $changed = [];

            foreach ($this->allCollectionUpdates[$em][$collection]['insertDiff'] as $entity) {
                $entityHash = $this->getEntityHash($entity);
                $inserted[$entityHash] = $insertions[$entityHash] ?? $this->convertEntityToArray($em, $entity, []);
            }

            foreach ($this->allCollectionUpdates[$em][$collection]['deleteDiff'] as $entity) {
                $entityHash = $this->getEntityHash($entity);
                $deleted[$entityHash] = $deletions[$entityHash] ?? $this->convertEntityToArray($em, $entity, []);
            }

            foreach ($this->allCollectionUpdates[$em][$collection]['changeDiff'] as $entity) {
                $entityHash = $this->getEntityHash($entity);
                $changed[$entityHash] = $updates[$entityHash] ?? $this->convertEntityToArray($em, $entity, []);
            }

            $ownerFieldName = $collection->getMapping()['fieldName'];
            $entityData = $this->convertEntityToArray($em, $collection->getOwner(), []);
            $entityData['change_set'][$ownerFieldName] = [
                ['deleted' => $deleted],
                ['inserted' => $inserted, 'changed' => $changed],
            ];

            if ($inserted || $deleted || $changed) {
                $collectionUpdates[spl_object_hash($collection->getOwner())] = $entityData;
            }
        }

        return $collectionUpdates;
    }

    /**
     * @param EntityManager $em
     * @param object $entity
     * @param array $changeSet
     * @param string|null $entityName
     * @return array
     */
    private function convertEntityToArray(EntityManager $em, $entity, array $changeSet, $entityName = null)
    {
        return $this->entityToArrayConverter->convertNamedEntityToArray($em, $entity, $changeSet, $entityName);
    }

    /**
     * @param AdditionalEntityChangesToAuditStorage $additionalEntityChangesStorage
     */
    public function setAdditionalEntityChangesStorage(
        AdditionalEntityChangesToAuditStorage $additionalEntityChangesStorage
    ) {
        $this->additionalEntityChangesStorage = $additionalEntityChangesStorage;
    }

    /**
     * @param EntityNameResolver $entityNameResolver
     */
    public function setEntityNameResolver(EntityNameResolver $entityNameResolver): void
    {
        $this->entityNameResolver = $entityNameResolver;
    }

    /**
     * @param AuditMessageBodyProvider $auditMessageBodyProvider
     */
    public function setAuditMessageBodyProvider(AuditMessageBodyProvider $auditMessageBodyProvider)
    {
        $this->auditMessageBodyProvider = $auditMessageBodyProvider;
    }

    /**
     * @param PropertyAccessor $propertyAccessor
     */
    public function setPropertyAccessor(PropertyAccessor $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @param object $entity
     * @return string
     */
    private function getEntityHash($entity): string
    {
        return spl_object_hash($entity);
    }
}
