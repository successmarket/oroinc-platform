<?php

namespace Oro\Bundle\ApiBundle\Processor\GetConfig;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\Extra\ConfigExtraInterface;
use Oro\Bundle\ApiBundle\Config\Extra\ConfigExtraSectionInterface;
use Oro\Bundle\ApiBundle\Config\Extra\ExpandRelatedEntitiesConfigExtra;
use Oro\Bundle\ApiBundle\Exception\NotSupportedConfigOperationException;
use Oro\Bundle\ApiBundle\Model\EntityIdentifier;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\CompleteCustomDataTypeHelper;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\EntityOverrideProviderInterface;
use Oro\Bundle\ApiBundle\Provider\EntityOverrideProviderRegistry;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Loads full configuration of the target entity for associations were requested to expand.
 * For example, in JSON:API the "include" filter can be used to request related entities.
 */
class ExpandRelatedEntities implements ProcessorInterface
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var ConfigProvider */
    private $configProvider;

    /** @var EntityOverrideProviderRegistry */
    private $entityOverrideProviderRegistry;

    /** @var CompleteCustomDataTypeHelper */
    private $customDataTypeHelper;

    /**
     * @param DoctrineHelper                 $doctrineHelper
     * @param ConfigProvider                 $configProvider
     * @param EntityOverrideProviderRegistry $entityOverrideProviderRegistry
     * @param CompleteCustomDataTypeHelper   $customDataTypeHelper
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        ConfigProvider $configProvider,
        EntityOverrideProviderRegistry $entityOverrideProviderRegistry,
        CompleteCustomDataTypeHelper $customDataTypeHelper
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->configProvider = $configProvider;
        $this->entityOverrideProviderRegistry = $entityOverrideProviderRegistry;
        $this->customDataTypeHelper = $customDataTypeHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $definition = $context->getResult();
        if ($definition->isExcludeAll()) {
            // already processed
            return;
        }

        $entityClass = $context->getClassName();
        if (!$definition->isInclusionEnabled()) {
            throw new NotSupportedConfigOperationException($entityClass, ExpandRelatedEntitiesConfigExtra::NAME);
        }

        if ($this->doctrineHelper->isManageableEntityClass($entityClass)) {
            $this->completeEntityAssociations(
                $this->doctrineHelper->getEntityMetadataForClass($entityClass),
                $definition,
                $context->get(ExpandRelatedEntitiesConfigExtra::NAME),
                $context->getVersion(),
                $context->getRequestType(),
                $context->getPropagableExtras()
            );
        } else {
            $this->completeObjectAssociations(
                $definition,
                $context->get(ExpandRelatedEntitiesConfigExtra::NAME),
                $context->getVersion(),
                $context->getRequestType(),
                $context->getPropagableExtras()
            );
        }
    }

    /**
     * @param ClassMetadata          $metadata
     * @param EntityDefinitionConfig $definition
     * @param string[]               $expandedEntities
     * @param string                 $version
     * @param RequestType            $requestType
     * @param ConfigExtraInterface[] $extras
     */
    private function completeEntityAssociations(
        ClassMetadata $metadata,
        EntityDefinitionConfig $definition,
        $expandedEntities,
        $version,
        RequestType $requestType,
        array $extras
    ) {
        $entityOverrideProvider = $this->entityOverrideProviderRegistry->getEntityOverrideProvider($requestType);
        $associations = $this->splitExpandedEntities($expandedEntities);
        foreach ($associations as $fieldName => $targetExpandedEntities) {
            if (!$definition->hasField($fieldName)
                && null !== $definition->findFieldNameByPropertyPath($fieldName)
            ) {
                continue;
            }

            $propertyPath = $this->getPropertyPath($fieldName, $definition);

            $lastDelimiter = strrpos($propertyPath, '.');
            if (false === $lastDelimiter) {
                $targetMetadata = $metadata;
                $targetFieldName = $propertyPath;
            } else {
                $targetMetadata = $this->doctrineHelper->findEntityMetadataByPath(
                    $metadata->name,
                    substr($propertyPath, 0, $lastDelimiter)
                );
                $targetFieldName = substr($propertyPath, $lastDelimiter + 1);
            }

            if (null !== $targetMetadata && $targetMetadata->hasAssociation($targetFieldName)) {
                $targetClass = $this->getAssociationTargetClass(
                    $targetMetadata,
                    $targetFieldName,
                    $entityOverrideProvider
                );
                $this->completeAssociation(
                    $definition,
                    $fieldName,
                    $targetClass,
                    $targetExpandedEntities,
                    $version,
                    $requestType,
                    $extras
                );
                $field = $definition->getField($fieldName);
                if (null !== $field && $field->getTargetClass()) {
                    $field->setTargetType(
                        ConfigUtil::getAssociationTargetType(
                            $targetMetadata->isCollectionValuedAssociation($targetFieldName)
                        )
                    );
                }
            } elseif ($definition->hasField($fieldName)) {
                $field = $definition->getField($fieldName);
                $targetClass = $field->getTargetClass();
                if ($targetClass) {
                    $dataType = $field->getDataType();
                    if ($dataType) {
                        $this->customDataTypeHelper->completeCustomDataType(
                            $definition,
                            $metadata,
                            $fieldName,
                            $field,
                            $dataType,
                            $version,
                            $requestType
                        );
                    }
                    $this->completeAssociation(
                        $definition,
                        $fieldName,
                        $targetClass,
                        $targetExpandedEntities,
                        $version,
                        $requestType,
                        $extras
                    );
                }
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string[]               $expandedEntities
     * @param string                 $version
     * @param RequestType            $requestType
     * @param ConfigExtraInterface[] $extras
     */
    private function completeObjectAssociations(
        EntityDefinitionConfig $definition,
        $expandedEntities,
        $version,
        RequestType $requestType,
        array $extras
    ) {
        $associations = $this->splitExpandedEntities($expandedEntities);
        foreach ($associations as $fieldName => $targetExpandedEntities) {
            $field = $definition->getField($fieldName);
            if (null !== $field) {
                $targetClass = $field->getTargetClass();
                if ($targetClass) {
                    $this->completeAssociation(
                        $definition,
                        $fieldName,
                        $targetClass,
                        $targetExpandedEntities,
                        $version,
                        $requestType,
                        $extras
                    );
                }
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $fieldName
     * @param string                 $targetClass
     * @param string[]               $targetExpandedEntities
     * @param string                 $version
     * @param RequestType            $requestType
     * @param ConfigExtraInterface[] $extras
     */
    private function completeAssociation(
        EntityDefinitionConfig $definition,
        $fieldName,
        $targetClass,
        $targetExpandedEntities,
        $version,
        RequestType $requestType,
        array $extras
    ) {
        if (!empty($targetExpandedEntities)) {
            $extras[] = new ExpandRelatedEntitiesConfigExtra($targetExpandedEntities);
        }

        $config = $this->configProvider->getConfig($targetClass, $version, $requestType, $extras);
        if ($config->hasDefinition()) {
            $targetEntity = $config->getDefinition();
            foreach ($extras as $extra) {
                if ($extra instanceof ConfigExtraSectionInterface) {
                    $sectionName = $extra->getName();
                    if ($config->has($sectionName)) {
                        $targetEntity->set($sectionName, $config->get($sectionName));
                    }
                }
            }
            $field = $definition->getOrAddField($fieldName);
            if (!$field->getTargetClass()) {
                $field->setTargetClass($targetClass);
            }
            $field->setTargetEntity($targetEntity);
        }
    }

    /**
     * @param string[] $expandedEntities
     *
     * @return array
     */
    private function splitExpandedEntities($expandedEntities)
    {
        $result = [];
        foreach ($expandedEntities as $expandedEntity) {
            $path = ConfigUtil::explodePropertyPath($expandedEntity);
            if (count($path) === 1) {
                $result[$expandedEntity] = [];
            } else {
                $fieldName = array_shift($path);
                $result[$fieldName][] = implode(ConfigUtil::PATH_DELIMITER, $path);
            }
        }

        return $result;
    }

    /**
     * @param string                 $fieldName
     * @param EntityDefinitionConfig $definition
     *
     * @return string
     */
    private function getPropertyPath($fieldName, EntityDefinitionConfig $definition)
    {
        if (!$definition->hasField($fieldName)) {
            return $fieldName;
        }

        return $definition->getField($fieldName)->getPropertyPath($fieldName);
    }

    /**
     * @param ClassMetadata                   $parentMetadata
     * @param string                          $associationName
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     *
     * @return string
     */
    private function getAssociationTargetClass(
        ClassMetadata $parentMetadata,
        string $associationName,
        EntityOverrideProviderInterface $entityOverrideProvider
    ): string {
        $entityClass = $parentMetadata->getAssociationTargetClass($associationName);
        // use EntityIdentifier as a target class for associations based on Doctrine's inheritance mapping
        $metadata = $this->doctrineHelper->getEntityMetadataForClass($entityClass);
        if (!$metadata->isInheritanceTypeNone()) {
            return EntityIdentifier::class;
        }

        return $this->resolveEntityClass($entityClass, $entityOverrideProvider);
    }

    /**
     * @param string                          $entityClass
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     *
     * @return string
     */
    private function resolveEntityClass(
        string $entityClass,
        EntityOverrideProviderInterface $entityOverrideProvider
    ): string {
        $substituteEntityClass = $entityOverrideProvider->getSubstituteEntityClass($entityClass);
        if ($substituteEntityClass) {
            return $substituteEntityClass;
        }

        return $entityClass;
    }
}
