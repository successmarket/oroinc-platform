<?php

namespace Oro\Bundle\ScopeBundle\EventListener;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Oro\Bundle\ScopeBundle\Entity\Scope;
use Oro\Bundle\ScopeBundle\Manager\ScopeCollection;

/**
 * The listener that does the following:
 * * on preFlush event calls EntityManager::persist() method for all Scope entities scheduled for insert
 * * on postFlush event invalidates scope cache when a Scope entity is created, updated or deleted
 *   or when a target entity of any association in Scope entity is deleted
 */
class DoctrineEventListener
{
    /** @var ScopeCollection */
    private $scheduledForInsertScopes;

    /** @var CacheProvider */
    private $scopeCache;

    /** @var bool */
    private $needToResetScopeCache = false;

    /**
     * @param ScopeCollection $scheduledForInsertScopes
     * @param CacheProvider   $scopeCache
     */
    public function __construct(ScopeCollection $scheduledForInsertScopes, CacheProvider $scopeCache)
    {
        $this->scheduledForInsertScopes = $scheduledForInsertScopes;
        $this->scopeCache = $scopeCache;
    }

    /**
     * @param PreFlushEventArgs $event
     */
    public function preFlush(PreFlushEventArgs $event)
    {
        $em = $event->getEntityManager();
        $metadataFactory = $em->getMetadataFactory();
        if (!$metadataFactory->hasMetadataFor(Scope::class)) {
            return;
        }

        if (!$this->scheduledForInsertScopes->isEmpty()) {
            $em = $event->getEntityManager();
            $scopes = $this->scheduledForInsertScopes->getAll();
            foreach ($scopes as $scope) {
                $em->persist($scope);
            }
            $this->scheduledForInsertScopes->clear();
        }
    }

    /**
     * @param OnFlushEventArgs $event
     */
    public function onFlush(OnFlushEventArgs $event)
    {
        $em = $event->getEntityManager();
        $metadataFactory = $em->getMetadataFactory();
        if (!$metadataFactory->hasMetadataFor(Scope::class)) {
            return;
        }

        if ($this->needToResetScopeCache) {
            // do nothing as we are already known that the cache should be reset
            return;
        }

        if ($this->isScopeCacheAffected($event->getEntityManager())) {
            $this->needToResetScopeCache = true;
        }
    }

    public function postFlush()
    {
        if ($this->needToResetScopeCache) {
            $this->needToResetScopeCache = false;
            $this->scopeCache->deleteAll();
        }
    }

    public function onClear()
    {
        $this->scheduledForInsertScopes->clear();
        $this->needToResetScopeCache = false;
    }

    /**
     * @param EntityManagerInterface $em
     *
     * @return bool
     */
    private function isScopeCacheAffected(EntityManagerInterface $em): bool
    {
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Scope) {
                return true;
            }
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Scope) {
                return true;
            }
        }
        $scheduledEntityDeletions = $uow->getScheduledEntityDeletions();
        if ($scheduledEntityDeletions) {
            $scopeEntityClasses = $this->getScopeEntityClasses($em);
            foreach ($scheduledEntityDeletions as $entity) {
                if (isset($scopeEntityClasses[ClassUtils::getClass($entity)])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param EntityManagerInterface $em
     *
     * @return array [entity class => TRUE, ...]
     */
    private function getScopeEntityClasses(EntityManagerInterface $em): array
    {
        $result = [];
        $result[Scope::class] = true;
        $scopeMetadata = $em->getClassMetadata(Scope::class);
        $associations = $scopeMetadata->getAssociationMappings();
        foreach ($associations as $association) {
            $targetEntity = $association['targetEntity'];
            if (!isset($result[$targetEntity])) {
                $result[$targetEntity] = true;
            }
        }

        return $result;
    }
}
