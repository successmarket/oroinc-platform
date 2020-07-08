<?php

namespace Oro\Bundle\WorkflowBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\PlatformBundle\EventListener\OptionalListenerInterface;
use Oro\Bundle\WorkflowBundle\Entity\EventTriggerInterface;
use Oro\Bundle\WorkflowBundle\EventListener\Extension\EventTriggerExtensionInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects event triggers when any entity is created, updated or removed.
 */
class EventTriggerCollectorListener implements OptionalListenerInterface, ResetInterface
{
    /** @var bool */
    private $enabled = true;

    /** @var bool */
    private $forceQueued = false;

    /** @var iterable|EventTriggerExtensionInterface[] */
    private $extensions;

    /** @var EventTriggerExtensionInterface[] */
    private $initializedExtensions;

    /**
     * @param iterable|EventTriggerExtensionInterface[] $extensions
     */
    public function __construct(iterable $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnabled($enabled = true)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * @param bool $forceQueued
     */
    public function setForceQueued(bool $forceQueued = false)
    {
        $this->forceQueued = $forceQueued;
        $this->reset();
    }

    /**
     * {@inheritDoc}
     */
    public function reset()
    {
        $this->initializedExtensions = null;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        $this->schedule($args->getEntity(), EventTriggerInterface::EVENT_CREATE);
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        $changeSet = $args->getEntityChangeSet();
        $fields = array_keys($changeSet);
        foreach ($fields as $field) {
            $changeSet[$field] = ['old' => $args->getOldValue($field), 'new' => $args->getNewValue($field)];
        }

        $this->schedule($args->getEntity(), EventTriggerInterface::EVENT_UPDATE, $changeSet);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        $this->schedule($args->getEntity(), EventTriggerInterface::EVENT_DELETE);
    }

    /**
     * @param OnClearEventArgs $args
     */
    public function onClear(OnClearEventArgs $args)
    {
        $entityClass = $args->clearsAllEntities() ? null : $args->getEntityClass();
        $extensions = $this->getExtensions();
        foreach ($extensions as $extension) {
            $extension->clear($entityClass);
        }
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (!$this->enabled) {
            return;
        }

        $extensions = $this->getExtensions();
        foreach ($extensions as $extension) {
            $extension->process($args->getEntityManager());
        }
    }

    /**
     * @param object $entity
     * @param string $event
     * @param array|null $changeSet
     */
    private function schedule($entity, $event, array $changeSet = null)
    {
        $extensions = $this->getExtensions();
        foreach ($extensions as $extension) {
            if ($extension->hasTriggers($entity, $event)) {
                $extension->schedule($entity, $event, $changeSet);
            }
        }
    }

    /**
     * @return EventTriggerExtensionInterface[]
     */
    private function getExtensions(): array
    {
        if (null === $this->initializedExtensions) {
            $this->initializedExtensions = [];
            foreach ($this->extensions as $extension) {
                $extension->setForceQueued($this->forceQueued);
                $this->initializedExtensions[] = $extension;
            }
        }

        return $this->initializedExtensions;
    }
}
