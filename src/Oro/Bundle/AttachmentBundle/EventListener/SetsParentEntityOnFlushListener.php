<?php

namespace Oro\Bundle\AttachmentBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Oro\Bundle\AttachmentBundle\Helper\FieldConfigHelper;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Acl\Util\ClassUtils;

/**
 * Fills parentEntityClass and parentEntityId in File entity after it is persisted or updated.
 */
class SetsParentEntityOnFlushListener
{
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;

    /** @var ConfigManager */
    private $configManager;

    /** @var \SplObjectStorage */
    private $scheduledForUpdate;

    /**
     * @param PropertyAccessorInterface $propertyAccessor
     * @param ConfigManager $configManager
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor, ConfigManager $configManager)
    {
        $this->propertyAccessor = $propertyAccessor;
        $this->configManager = $configManager;
        $this->scheduledForUpdate = new \SplObjectStorage();
    }

    /**
     * Sets parent class name, id and field name for File entities.
     *
     * @param OnFlushEventArgs $event
     */
    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getEntityManager();
        $metadataFactory = $entityManager->getMetadataFactory();
        if (!$metadataFactory->hasMetadataFor(File::class)) {
            return;
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        $entities = $unitOfWork->getScheduledEntityUpdates();
        $fileClassMetadata = $metadataFactory->getMetadataFor(File::class);

        foreach ($entities as $entity) {
            $entityClass = ClassUtils::getRealClass($entity);
            $entityId = $this->getEntityId($entityManager, $entity);

            if (!$entityId) {
                // Entity does not have id.
                continue;
            }

            $this->processEntity(
                $entity,
                $entityManager,
                static function (
                    $entity,
                    string $fieldName,
                    array $files
                ) use (
                    $unitOfWork,
                    $entityClass,
                    $entityId,
                    $fileClassMetadata
                ) {
                    /** @var File $file */
                    foreach ($files as $file) {
                        $file
                            ->setParentEntityClass($entityClass)
                            ->setParentEntityId($entityId)
                            ->setParentEntityFieldName($fieldName);

                        $unitOfWork->recomputeSingleEntityChangeSet($fileClassMetadata, $file);
                    }
                }
            );
        }

        $this->onFlushCollections($event);
    }

    /**
     * @param OnFlushEventArgs $event
     */
    private function onFlushCollections(OnFlushEventArgs $event)
    {
        $entityManager = $event->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $fileClassMetadata = $entityManager->getClassMetadata(File::class);

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            /* @var $collection PersistentCollection */
            $entity = $collection->getOwner();

            $entityClass = ClassUtils::getRealClass($entity);
            $entityId = $this->getEntityId($entityManager, $entity);
            if (!$entityId) {
                continue;
            }

            /* @var $fieldConfigs Config[] */
            $fieldConfigs = $this->configManager->getConfigs('extend', $entityClass);
            foreach ($fieldConfigs as $fieldConfig) {
                /* @var $fieldConfigId FieldConfigId */
                $fieldConfigId = $fieldConfig->getId();
                if (!FieldConfigHelper::isMultiField($fieldConfigId)) {
                    continue;
                }

                $fieldName = $fieldConfigId->getFieldName();
                if ($this->propertyAccessor->getValue($entity, $fieldName) !== $collection) {
                    continue;
                }

                foreach ($collection as $fileItem) {
                    /* @var $fileItem FileItem */
                    $file = $fileItem->getFile();
                    if ($file->getId()) {
                        continue;
                    }

                    $file->setParentEntityClass($entityClass)
                        ->setParentEntityId($entityId)
                        ->setParentEntityFieldName($fieldName);

                    $unitOfWork->recomputeSingleEntityChangeSet($fileClassMetadata, $file);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @param EntityManager $entityManager
     * @param callable $callback
     */
    private function processEntity($entity, EntityManager $entityManager, callable $callback): void
    {
        $classMetadata = $entityManager->getClassMetadata(ClassUtils::getRealClass($entity));
        if (count($classMetadata->getIdentifier()) !== 1) {
            // Entity does not have id field or it is composite.
            return;
        }

        foreach ($classMetadata->getAssociationMappings() as $mapping) {
            if ($entity instanceof FileItem || !$this->isMetadataAcceptable($mapping)) {
                continue;
            }

            $fileEntities = $this->getFileFieldValue($entity, $mapping['fieldName'], $mapping['type']);
            if (!$fileEntities) {
                continue;
            }

            // Filters only File entities without parent entity class.
            $fileEntities = array_filter($fileEntities, static function (File $file) {
                return !$file->getParentEntityClass();
            });

            // Skips field when no File entities are going to be persisted.
            if (!$fileEntities) {
                continue;
            }

            $callback($entity, $mapping['fieldName'], $fileEntities);
        }
    }

    /**
     * @param array $mapping
     * @return bool
     */
    private function isMetadataAcceptable(array $mapping): bool
    {
        return ($mapping['isOwningSide'] && $mapping['targetEntity'] === File::class) ||
            (!$mapping['isOwningSide'] && $mapping['targetEntity'] === FileItem::class);
    }

    /**
     * Schedules for update the File entities which should be updated with parent class name, id and field name.
     *
     * @param LifecycleEventArgs $event
     */
    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->processEntity(
            $event->getEntity(),
            $event->getEntityManager(),
            function ($entity, string $fieldName, array $filesEntities) {
                if (!isset($this->scheduledForUpdate[$entity])) {
                    $this->scheduledForUpdate[$entity] = [];
                }

                $fields = $this->scheduledForUpdate[$entity];
                $fields[$fieldName] = $filesEntities;
                $this->scheduledForUpdate[$entity] = $fields;
            }
        );
    }

    /**
     * Applies the scheduled updates of the File entities.
     *
     * @param LifecycleEventArgs $event
     */
    public function postPersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getEntity();
        if (!$this->scheduledForUpdate->contains($entity)) {
            return;
        }

        $entityManager = $event->getEntityManager();
        $entityId = $this->getEntityId($entityManager, $entity);
        if (!$entityId) {
            throw new \LogicException('The persisted entity does not have an id');
        }

        $entityClass = ClassUtils::getRealClass($entity);
        foreach ($this->scheduledForUpdate[$entity] as $fieldName => $fileEntities) {
            /** @var File $file */
            foreach ($fileEntities as $file) {
                $this->applyExtraUpdates(
                    $entityManager,
                    $file,
                    [
                        'parentEntityClass' => [$file->getParentEntityClass(), $entityClass],
                        'parentEntityId' => [$file->getParentEntityId(), $entityId],
                        'parentEntityFieldName' => [$file->getParentEntityFieldName(), $fieldName],
                    ]
                );
            }
        }

        $this->scheduledForUpdate->detach($entity);
    }

    /**
     * @param object $entity
     * @param string $fieldName
     * @param string $associationType
     *
     * @return array
     */
    private function getFileFieldValue($entity, string $fieldName, string $associationType): array
    {
        $value = $this->propertyAccessor->getValue($entity, $fieldName);

        if ($associationType & ClassMetadata::TO_MANY) {
            // Field value is Collection of File entities.
            $value = array_map(
                static function ($obj) {
                    return $obj instanceof FileItem ? $obj->getFile() : $obj;
                },
                $value->toArray()
            );
        } else {
            $value = $value ? [$value] : [];
        }

        return $value;
    }

    /**
     * @param EntityManager $entityManager
     * @param object $entity
     *
     * @return mixed|null
     */
    private function getEntityId(EntityManager $entityManager, $entity)
    {
        $classMetadata = $entityManager->getClassMetadata(ClassUtils::getRealClass($entity));
        $identifierFields = $classMetadata->getIdentifier();

        if (count($identifierFields) !== 1) {
            // Entity does not have id field or it is composite.
            return null;
        }

        try {
            return $this->propertyAccessor->getValue($entity, current($identifierFields));
        } catch (NoSuchPropertyException $e) {
            // Id field does not have getter.
            return null;
        }
    }

    /**
     * @param EntityManager $entityManager
     * @param File $file
     * @param array $extraUpdateChangeSet
     */
    private function applyExtraUpdates(EntityManager $entityManager, File $file, array $extraUpdateChangeSet): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($extraUpdateChangeSet as $changedFieldName => $change) {
            $this->propertyAccessor->setValue($file, $changedFieldName, $change[1]);
            $unitOfWork->propertyChanged($file, $changedFieldName, $change[0], $change[1]);
        }

        $unitOfWork->scheduleExtraUpdate($file, $extraUpdateChangeSet);
        $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(File::class), $file);
    }
}
