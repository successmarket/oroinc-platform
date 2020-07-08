<?php

namespace Oro\Bundle\WorkflowBundle\Validator;

use Oro\Bundle\EntityBundle\Tools\DatabaseChecker;
use Oro\Bundle\WorkflowBundle\Model\WorkflowPermissionRegistry;
use Oro\Bundle\WorkflowBundle\Restriction\RestrictionManager;
use Oro\Bundle\WorkflowBundle\Validator\Constraints\WorkflowEntity;
use Psr\Container\ContainerInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AbstractLoader;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Adds WorkflowEntity validation constraint to workflow related entities.
 */
class WorkflowValidationLoader extends AbstractLoader implements ServiceSubscriberInterface
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
            'oro_workflow.database_checker'    => DatabaseChecker::class,
            'oro_workflow.permission_registry' => WorkflowPermissionRegistry::class,
            'oro_workflow.restriction.manager' => RestrictionManager::class
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        if (!$this->getDatabaseChecker()->checkDatabase()) {
            return false;
        }

        if (!$this->isWorkflowEntityConstraintRequired($metadata->getClassName())) {
            return false;
        }

        $metadata->addConstraint($this->newConstraint(WorkflowEntity::class));

        return true;
    }

    /**
     * @param string $className
     *
     * @return bool
     */
    private function isWorkflowEntityConstraintRequired(string $className): bool
    {
        return
            $this->getPermissionRegistry()->supportsClass($className, false)
            || $this->getRestrictionManager()->hasEntityClassRestrictions($className, false);
    }

    /**
     * @return DatabaseChecker
     */
    private function getDatabaseChecker(): DatabaseChecker
    {
        return $this->container->get('oro_workflow.database_checker');
    }

    /**
     * @return WorkflowPermissionRegistry
     */
    private function getPermissionRegistry(): WorkflowPermissionRegistry
    {
        return $this->container->get('oro_workflow.permission_registry');
    }

    /**
     * @return RestrictionManager
     */
    private function getRestrictionManager(): RestrictionManager
    {
        return $this->container->get('oro_workflow.restriction.manager');
    }
}
