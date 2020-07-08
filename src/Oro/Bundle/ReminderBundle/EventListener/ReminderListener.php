<?php

namespace Oro\Bundle\ReminderBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Oro\Bundle\ReminderBundle\Entity\Manager\ReminderManager;
use Oro\Bundle\ReminderBundle\Entity\RemindableInterface;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Handles entities that implements RemindableInterface.
 */
class ReminderListener implements EventSubscriber, ServiceSubscriberInterface
{
    /** @var ContainerInterface */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices()
    {
        return [
            'oro_reminder.entity.manager' => ReminderManager::class
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postLoad,
            Events::postPersist
        ];
    }

    /**
     * After entity with reminders was loaded, load reminders
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof RemindableInterface) {
            $this->getReminderManager()->loadReminders($entity);
        }
    }

    /**
     * Save reminders for new entities
     *
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event)
    {
        $entity = $event->getEntity();
        if ($entity instanceof RemindableInterface) {
            $this->getReminderManager()->saveReminders($entity);
        }
    }

    /**
     * @return ReminderManager
     */
    private function getReminderManager(): ReminderManager
    {
        return $this->container->get('oro_reminder.entity.manager');
    }
}
