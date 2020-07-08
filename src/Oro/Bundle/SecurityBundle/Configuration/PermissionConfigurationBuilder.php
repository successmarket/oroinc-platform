<?php

namespace Oro\Bundle\SecurityBundle\Configuration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityBundle\Exception\NotManageableEntityException;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Entity\Permission;
use Oro\Bundle\SecurityBundle\Entity\PermissionEntity;
use Oro\Bundle\SecurityBundle\Exception\MissedRequiredOptionException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Builds Permissions by configuration array
 */
class PermissionConfigurationBuilder
{
    /**
     * @var DoctrineHelper
     */
    private $doctrineHelper;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var array
     */
    private $processedEntities = [];

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param ValidatorInterface $validator
     * @param EntityManager $entityManager
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        ValidatorInterface $validator,
        EntityManager $entityManager
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
    }

    /**
     * @param array $configuration
     * @return Permission[]|Collection
     */
    public function buildPermissions(array $configuration)
    {
        $classNames = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $permissions = new ArrayCollection();
        foreach ($configuration as $name => $permissionConfiguration) {
            $permission = $this->buildPermission($name, $permissionConfiguration, $classNames);

            $violations = $this->validator->validate($permission);
            if ($violations->count() > 0) {
                throw $this->createValidationException($name, $violations);
            }

            $permissions->add($permission);
        }

        $this->processedEntities = [];

        return $permissions;
    }

    /**
     * @param string $name
     * @param array $configuration
     * @param array $classNames
     * @return Permission
     */
    protected function buildPermission($name, array $configuration, array $classNames): Permission
    {
        $this->assertConfigurationOptions($configuration, ['label']);

        $excludeEntities = $this->getConfigurationOption($configuration, 'exclude_entities', []);
        $applyToEntities = $this->getConfigurationOption($configuration, 'apply_to_entities', []);
        $applyToInterfaces = $this->getConfigurationOption($configuration, 'apply_to_interfaces', []);
        if (!empty($applyToInterfaces)) {
            $applyToEntitiesByInterfaces = $this->getClassesByInterfaces($classNames, $applyToInterfaces);
            $applyToEntities = array_merge($applyToEntities, $applyToEntitiesByInterfaces);
        }

        $permission = new Permission();
        $permission
            ->setName($name)
            ->setLabel($configuration['label'])
            ->setApplyToAll($this->getConfigurationOption($configuration, 'apply_to_all', true))
            ->setGroupNames($this->getConfigurationOption($configuration, 'group_names', []))
            ->setExcludeEntities($this->buildPermissionEntities($excludeEntities))
            ->setApplyToEntities($this->buildPermissionEntities($applyToEntities))
            ->setDescription($this->getConfigurationOption($configuration, 'description', ''));

        return $permission;
    }

    /**
     * @param array $configuration
     * @return ArrayCollection|PermissionEntity[]
     * @throws NotManageableEntityException
     */
    protected function buildPermissionEntities(array $configuration)
    {
        $repository = $this->doctrineHelper->getEntityRepositoryForClass(PermissionEntity::class);

        $entities = new ArrayCollection();
        $configuration = array_unique($configuration);
        foreach ($configuration as $entityName) {
            $entityNameNormalized = strtolower($entityName);

            if (!array_key_exists($entityNameNormalized, $this->processedEntities)) {
                $permissionEntity = $repository->findOneBy(['name' => $entityName]);

                if (!$permissionEntity) {
                    $permissionEntity = new PermissionEntity();
                    $permissionEntity->setName($entityName);
                }

                $this->processedEntities[$entityNameNormalized] = $permissionEntity;
            }

            $entities->add($this->processedEntities[$entityNameNormalized]);
        }

        return $entities;
    }

    /**
     * @param array $configuration
     * @param array $requiredOptions
     * @throws MissedRequiredOptionException
     */
    protected function assertConfigurationOptions(array $configuration, array $requiredOptions)
    {
        foreach ($requiredOptions as $optionName) {
            if (!isset($configuration[$optionName])) {
                throw new MissedRequiredOptionException(sprintf('Configuration option "%s" is required', $optionName));
            }
        }
    }

    /**
     * @param array $options
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfigurationOption(array $options, $key, $default = null)
    {
        if (array_key_exists($key, $options)) {
            return $options[$key];
        }

        return $default;
    }

    /**
     * @param string $name
     * @param ConstraintViolationListInterface $violations
     * @return ValidatorException
     */
    protected function createValidationException($name, ConstraintViolationListInterface $violations)
    {
        $errors = '';

        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $errors .= sprintf('    %s%s', $violation->getMessage(), PHP_EOL);
        }

        return new ValidatorException(
            sprintf('Configuration of permission %s is invalid:%s%s', $name, PHP_EOL, $errors)
        );
    }

    /**
     * @param array $classNames
     * @param array $configuration
     * @return array
     */
    private function getClassesByInterfaces(array $classNames, array $configuration): array
    {
        return array_filter(
            $classNames,
            function ($class) use ($configuration) {
                foreach ($configuration as $interfaceName) {
                    $isSubClass = is_subclass_of($class, $interfaceName);
                    if ($isSubClass) {
                        return true;
                    }
                }

                return false;
            }
        );
    }
}
