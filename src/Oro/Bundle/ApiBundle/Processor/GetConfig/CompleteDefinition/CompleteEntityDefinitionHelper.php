<?php

namespace Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionFieldConfig;
use Oro\Bundle\ApiBundle\Config\Extra\ConfigExtraInterface;
use Oro\Bundle\ApiBundle\Config\Extra\ExpandRelatedEntitiesConfigExtra;
use Oro\Bundle\ApiBundle\Config\Extra\FilterIdentifierFieldsConfigExtra;
use Oro\Bundle\ApiBundle\Model\EntityIdentifier;
use Oro\Bundle\ApiBundle\Processor\GetConfig\ConfigContext;
use Oro\Bundle\ApiBundle\Provider\EntityOverrideProviderInterface;
use Oro\Bundle\ApiBundle\Provider\EntityOverrideProviderRegistry;
use Oro\Bundle\ApiBundle\Provider\ExclusionProviderRegistry;
use Oro\Bundle\ApiBundle\Provider\ExpandedAssociationExtractor;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ApiBundle\Util\EntityIdHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

/**
 * The helper class to complete the configuration of API resource based on ORM entity.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class CompleteEntityDefinitionHelper
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var EntityOverrideProviderRegistry */
    private $entityOverrideProviderRegistry;

    /** @var EntityIdHelper */
    private $entityIdHelper;

    /** @var CompleteAssociationHelper */
    private $associationHelper;

    /** @var CompleteCustomDataTypeHelper */
    private $customDataTypeHelper;

    /** @var ExclusionProviderRegistry */
    private $exclusionProviderRegistry;

    /** @var ExpandedAssociationExtractor */
    private $expandedAssociationExtractor;

    /** @var ConfigManager */
    private $configManager;

    /**
     * @param DoctrineHelper                 $doctrineHelper
     * @param EntityOverrideProviderRegistry $entityOverrideProviderRegistry
     * @param EntityIdHelper                 $entityIdHelper
     * @param CompleteAssociationHelper      $associationHelper
     * @param CompleteCustomDataTypeHelper   $customDataTypeHelper
     * @param ExclusionProviderRegistry      $exclusionProviderRegistry
     * @param ExpandedAssociationExtractor   $expandedAssociationExtractor
     * @param ConfigManager                  $configManager
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        EntityOverrideProviderRegistry $entityOverrideProviderRegistry,
        EntityIdHelper $entityIdHelper,
        CompleteAssociationHelper $associationHelper,
        CompleteCustomDataTypeHelper $customDataTypeHelper,
        ExclusionProviderRegistry $exclusionProviderRegistry,
        ExpandedAssociationExtractor $expandedAssociationExtractor,
        ConfigManager $configManager
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->entityOverrideProviderRegistry = $entityOverrideProviderRegistry;
        $this->entityIdHelper = $entityIdHelper;
        $this->associationHelper = $associationHelper;
        $this->customDataTypeHelper = $customDataTypeHelper;
        $this->exclusionProviderRegistry = $exclusionProviderRegistry;
        $this->expandedAssociationExtractor = $expandedAssociationExtractor;
        $this->configManager = $configManager;
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ConfigContext          $context
     */
    public function completeDefinition(
        EntityDefinitionConfig $definition,
        ConfigContext $context
    ) {
        $entityClass = $context->getClassName();
        /** @var ClassMetadata $metadata */
        $metadata = $this->doctrineHelper->getEntityMetadataForClass($entityClass);
        if ($context->hasExtra(FilterIdentifierFieldsConfigExtra::NAME)) {
            $this->completeIdentifierFields($definition, $metadata);
        } else {
            $version = $context->getVersion();
            $requestType = $context->getRequestType();
            $expandedEntities = $this->getExpandedEntities(
                $definition,
                $context->get(ExpandRelatedEntitiesConfigExtra::NAME)
            );
            $entityOverrideProvider = $this->entityOverrideProviderRegistry->getEntityOverrideProvider($requestType);
            $skipNotConfiguredCustomFields =
                $definition->getExclusionPolicy() === ConfigUtil::EXCLUSION_POLICY_CUSTOM_FIELDS
                && $this->isExtendSystemEntity($entityClass);
            $this->customDataTypeHelper->completeCustomDataTypes($definition, $metadata, $version, $requestType);
            $existingFields = $this->getExistingFields($definition);
            $this->completeUnidirectionalAssociations(
                $definition,
                $metadata,
                $expandedEntities,
                $version,
                $requestType
            );
            $this->completeAssociations(
                $entityOverrideProvider,
                $definition,
                $metadata,
                $existingFields,
                $expandedEntities,
                $skipNotConfiguredCustomFields,
                $version,
                $requestType
            );
            $this->completeFields(
                $definition,
                $metadata,
                $existingFields,
                $skipNotConfiguredCustomFields,
                $requestType
            );
            $this->completeDependentAssociations(
                $entityOverrideProvider,
                $definition,
                $metadata,
                $entityClass,
                $version,
                $requestType
            );
        }
        // make sure that identifier field names are set
        $idFieldNames = $definition->getIdentifierFieldNames();
        if (empty($idFieldNames)) {
            $this->setIdentifierFieldNames($definition, $metadata);
        } else {
            $this->completeCustomIdentifier($definition, $metadata, $context->getTargetAction());
        }
        // make sure "class name" meta field is added for entity with table inheritance
        if (!$metadata->isInheritanceTypeNone()) {
            $this->addClassNameField($definition);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     *
     * @return array [property path => field name, ...]
     */
    private function getExistingFields(EntityDefinitionConfig $definition)
    {
        $existingFields = [];
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            $propertyPath = $field->getPropertyPath($fieldName);
            if (ConfigUtil::IGNORE_PROPERTY_PATH !== $propertyPath) {
                $existingFields[$propertyPath] = $fieldName;
            }
        }

        return $existingFields;
    }

    /**
     * @param EntityDefinitionConfig $definition
     */
    private function addClassNameField(EntityDefinitionConfig $definition)
    {
        $classNameField = $definition->findFieldNameByPropertyPath(ConfigUtil::CLASS_NAME);
        if (null === $classNameField) {
            $classNameField = $definition->addField(ConfigUtil::CLASS_NAME);
            $classNameField->setMetaProperty(true);
            $classNameField->setDataType(DataType::STRING);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ClassMetadata          $metadata
     */
    private function setIdentifierFieldNames(EntityDefinitionConfig $definition, ClassMetadata $metadata)
    {
        $idFieldNames = [];
        $propertyPaths = $metadata->getIdentifierFieldNames();
        foreach ($propertyPaths as $propertyPath) {
            $idFieldNames[] = $definition->findFieldNameByPropertyPath($propertyPath) ?: $propertyPath;
        }
        $definition->setIdentifierFieldNames($idFieldNames);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ClassMetadata          $metadata
     * @param string|null            $targetAction
     */
    private function completeCustomIdentifier(
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        $targetAction
    ) {
        if ($metadata->usesIdGenerator()
            && (ApiAction::CREATE === $targetAction || ApiAction::UPDATE === $targetAction)
            && !$this->entityIdHelper->isEntityIdentifierEqual($metadata->getIdentifierFieldNames(), $definition)
        ) {
            $propertyPaths = $metadata->getIdentifierFieldNames();
            foreach ($propertyPaths as $propertyPath) {
                $field = $definition->findField($propertyPath, true);
                if (null !== $field) {
                    $formOptions = $field->getFormOptions();
                    if (null === $formOptions || !\array_key_exists('mapped', $formOptions)) {
                        $formOptions['mapped'] = false;
                        $field->setFormOptions($formOptions);
                    }
                }
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ClassMetadata          $metadata
     */
    private function completeIdentifierFields(
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata
    ) {
        // get identifier fields
        $configuredIdFieldNames = $definition->getIdentifierFieldNames();
        if (empty($configuredIdFieldNames)) {
            $idFieldNames = $metadata->getIdentifierFieldNames();
        } else {
            $idFieldNames = [];
            foreach ($configuredIdFieldNames as $fieldName) {
                $field = $definition->getField($fieldName);
                if (null !== $field) {
                    $propertyPath = $field->getPropertyPath();
                    if ($propertyPath) {
                        $fieldName = $propertyPath;
                    }
                }
                $idFieldNames[] = $fieldName;
            }
        }
        // remove all not identifier fields
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            if (!$field->isMetaProperty() && !\in_array($field->getPropertyPath($fieldName), $idFieldNames, true)) {
                $definition->removeField($fieldName);
            }
        }
        // make sure all identifier fields are added
        foreach ($idFieldNames as $propertyPath) {
            if (null === $definition->findField($propertyPath, true)) {
                $definition->addField($propertyPath);
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ClassMetadata          $metadata
     * @param array                  $existingFields [property path => field name, ...]
     * @param bool                   $skipNotConfiguredCustomFields
     * @param RequestType            $requestType
     */
    private function completeFields(
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        array $existingFields,
        $skipNotConfiguredCustomFields,
        RequestType $requestType
    ) {
        $exclusionProvider = $this->exclusionProviderRegistry->getExclusionProvider($requestType);
        $fieldNames = $metadata->getFieldNames();
        foreach ($fieldNames as $propertyPath) {
            if ($skipNotConfiguredCustomFields
                && !isset($existingFields[$propertyPath])
                && $this->isCustomField($metadata->name, $propertyPath)
            ) {
                continue;
            }

            if (isset($existingFields[$propertyPath])) {
                $field = $definition->getField($existingFields[$propertyPath]);
            } else {
                $field = $this->getOrAddNotComputedField($definition, $propertyPath);
            }
            if (null === $field) {
                continue;
            }

            if (!$field->hasExcluded()
                && !$field->isExcluded()
                && $exclusionProvider->isIgnoredField($metadata, $propertyPath)
            ) {
                $field->setExcluded();
            }
        }
    }

    /**
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     * @param EntityDefinitionConfig          $definition
     * @param ClassMetadata                   $metadata
     * @param array                           $existingFields   [property path => field name, ...]
     * @param array                           $expandedEntities [field name => [path, ...], ...]
     * @param bool                            $skipNotConfiguredCustomFields
     * @param string                          $version
     * @param RequestType                     $requestType
     */
    private function completeAssociations(
        EntityOverrideProviderInterface $entityOverrideProvider,
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        array $existingFields,
        array $expandedEntities,
        $skipNotConfiguredCustomFields,
        $version,
        RequestType $requestType
    ) {
        $exclusionProvider = $this->exclusionProviderRegistry->getExclusionProvider($requestType);
        $associations = $metadata->getAssociationMappings();
        foreach ($associations as $propertyPath => $mapping) {
            if ($skipNotConfiguredCustomFields
                && !isset($existingFields[$propertyPath])
                && $this->isCustomAssocation($metadata->name, $propertyPath)
            ) {
                continue;
            }

            if (isset($existingFields[$propertyPath])) {
                $fieldName = $existingFields[$propertyPath];
                $field = $definition->getField($fieldName);
            } else {
                $field = $this->getOrAddNotComputedField($definition, $propertyPath);
                if (null !== $field) {
                    $fieldName = $propertyPath;
                }
            }
            if (null === $field) {
                continue;
            }

            if (!$field->hasExcluded()
                && !$field->isExcluded()
                && $exclusionProvider->isIgnoredRelation($metadata, $propertyPath)
            ) {
                $field->setExcluded();
            }
            $this->completeAssociation(
                $entityOverrideProvider,
                $field,
                $mapping,
                $version,
                $requestType,
                $this->getAssociationConfigExtras($fieldName, $expandedEntities)
            );
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ClassMetadata          $metadata
     * @param array                  $expandedEntities [field name => [path, ...], ...]
     * @param string                 $version
     * @param RequestType            $requestType
     */
    private function completeUnidirectionalAssociations(
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        array $expandedEntities,
        $version,
        RequestType $requestType
    ) {
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            $targetClass = $field->getTargetClass();
            if ($targetClass && !$field->hasDataType()) {
                $propertyPath = $field->getPropertyPath($fieldName);
                if (!$metadata->hasAssociation($propertyPath)) {
                    $this->associationHelper->completeAssociation(
                        $field,
                        $targetClass,
                        $version,
                        $requestType,
                        $this->getAssociationConfigExtras($fieldName, $expandedEntities)
                    );
                }
            }
        }
    }

    /**
     * @param string $fieldName
     * @param array  $expandedEntities [field name => [path, ...], ...]
     *
     * @return array
     */
    private function getAssociationConfigExtras($fieldName, array $expandedEntities)
    {
        $extras = [];
        if (!empty($expandedEntities[$fieldName])) {
            $extras[] = new ExpandRelatedEntitiesConfigExtra($expandedEntities[$fieldName]);
        }

        return $extras;
    }

    /**
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     * @param EntityDefinitionFieldConfig     $field
     * @param array                           $associationMapping
     * @param string                          $version
     * @param RequestType                     $requestType
     * @param ConfigExtraInterface[]          $extras
     */
    private function completeAssociation(
        EntityOverrideProviderInterface $entityOverrideProvider,
        EntityDefinitionFieldConfig $field,
        array $associationMapping,
        $version,
        RequestType $requestType,
        array $extras
    ) {
        $this->associationHelper->completeAssociation(
            $field,
            $this->resolveAssociationEntityClass($associationMapping['targetEntity'], $entityOverrideProvider),
            $version,
            $requestType,
            $extras
        );
        if ($field->getTargetClass()) {
            $field->setTargetType(
                $this->associationHelper->getAssociationTargetType(
                    !($associationMapping['type'] & ClassMetadata::TO_ONE)
                )
            );
        }
    }

    /**
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     * @param EntityDefinitionConfig          $definition
     * @param ClassMetadata                   $metadata
     * @param string                          $entityClass
     * @param string                          $version
     * @param RequestType                     $requestType
     */
    private function completeDependentAssociations(
        EntityOverrideProviderInterface $entityOverrideProvider,
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        $entityClass,
        $version,
        RequestType $requestType
    ) {
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            $propertyPath = $field->getPropertyPath();
            if ($propertyPath && false !== \strpos($propertyPath, ConfigUtil::PATH_DELIMITER)) {
                try {
                    $this->completeDependentAssociation(
                        $entityOverrideProvider,
                        $definition,
                        $metadata,
                        $propertyPath,
                        $version,
                        $requestType
                    );
                } catch (\Exception $e) {
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot resolve property path "%1$s" specified for "%2$s::%3$s".'
                            . ' Check "property_path" option for this field.'
                            . ' If it is correct you can rename the target property as a possible solution.'
                            . ' For example:%4$s'
                            . '_%3$s:%4$s    property_path: %3$s',
                            $propertyPath,
                            $entityClass,
                            $fieldName,
                            "\n"
                        ),
                        0,
                        $e
                    );
                }
                $formOptions = $field->getFormOptions();
                if (null === $formOptions || !\array_key_exists('mapped', $formOptions)) {
                    $formOptions['mapped'] = false;
                    $field->setFormOptions($formOptions);
                }
            }
            $dependsOn = $field->getDependsOn();
            if (!empty($dependsOn)) {
                $this->resolveDependsOn(
                    $entityOverrideProvider,
                    $definition,
                    $metadata,
                    $entityClass,
                    $fieldName,
                    $dependsOn,
                    $version,
                    $requestType
                );
            }
        }
    }

    /**
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     * @param EntityDefinitionConfig          $definition
     * @param ClassMetadata                   $metadata
     * @param string                          $entityClass
     * @param string                          $fieldName
     * @param string[]                        $dependsOn
     * @param string                          $version
     * @param RequestType                     $requestType
     */
    private function resolveDependsOn(
        EntityOverrideProviderInterface $entityOverrideProvider,
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        $entityClass,
        $fieldName,
        array $dependsOn,
        $version,
        RequestType $requestType
    ) {
        foreach ($dependsOn as $dependsOnFieldName) {
            try {
                $this->completeDependentAssociation(
                    $entityOverrideProvider,
                    $definition,
                    $metadata,
                    $dependsOnFieldName,
                    $version,
                    $requestType
                );
            } catch (\Exception $e) {
                $hintMessage = 'Check "depends_on" option for this field.';
                if ($dependsOnFieldName === $fieldName) {
                    $hintMessage .= sprintf(
                        ' If the value of this option is correct you can declare an excluded field'
                        . ' with "%1$s" property path. For example:%2$s'
                        . '_%1$s:%2$s    property_path: %1$s%2$s    exclude: true',
                        $dependsOnFieldName,
                        "\n"
                    );
                }
                throw new \RuntimeException(
                    sprintf(
                        'Cannot resolve dependency to "%s" specified for "%s::%s". %s',
                        $dependsOnFieldName,
                        $entityClass,
                        $fieldName,
                        $hintMessage
                    ),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     * @param EntityDefinitionConfig          $definition
     * @param ClassMetadata                   $metadata
     * @param string                          $propertyPath
     * @param string                          $version
     * @param RequestType                     $requestType
     */
    private function completeDependentAssociation(
        EntityOverrideProviderInterface $entityOverrideProvider,
        EntityDefinitionConfig $definition,
        ClassMetadata $metadata,
        $propertyPath,
        $version,
        RequestType $requestType
    ) {
        $targetClass = $this->resolveEntityClass($metadata->name, $entityOverrideProvider);
        $targetDefinition = $definition;
        $targetMetadata = $metadata;
        $path = ConfigUtil::explodePropertyPath($propertyPath);
        $pathLength = \count($path);
        $i = 0;
        foreach ($path as $targetPropertyName) {
            $targetField = $targetDefinition->findField($targetPropertyName, true);
            if (null === $targetField) {
                $targetFullDefinition = $this->loadFullDefinition($targetClass, $version, $requestType);
                $targetFieldName = $targetDefinition->findFieldNameByPropertyPath($targetPropertyName);
                if ($targetFieldName) {
                    $targetField = $targetDefinition->getField($targetFieldName);
                } else {
                    $targetField = $targetDefinition->addField(
                        $targetPropertyName,
                        $targetFullDefinition->findField($targetPropertyName, true)
                    );
                    $targetField->setExcluded();
                }
                $targetDependsOn = $targetField->getDependsOn();
                if (!empty($targetDependsOn)) {
                    $this->resolveDependsOn(
                        $entityOverrideProvider,
                        $targetDefinition,
                        $this->doctrineHelper->getEntityMetadataForClass($targetClass),
                        $targetClass,
                        $targetFieldName,
                        $targetDependsOn,
                        $version,
                        $requestType
                    );
                }
            }

            $i++;
            if ($i >= $pathLength) {
                break;
            }

            $targetClass = $targetField->getTargetClass();
            if (!$targetClass && null !== $targetMetadata && $targetMetadata->hasAssociation($targetPropertyName)) {
                $targetClass = $targetMetadata->getAssociationTargetClass($targetPropertyName);
                $targetMetadata = $this->doctrineHelper->getEntityMetadataForClass($targetClass, false);
                $targetClass = $this->resolveEntityClass($targetClass, $entityOverrideProvider);
            }

            if (!$targetClass) {
                break;
            }

            $targetDefinition = $targetField->getOrCreateTargetEntity();
        }
    }

    /**
     * @param string      $entityClass
     * @param string      $version
     * @param RequestType $requestType
     *
     * @return EntityDefinitionConfig
     */
    private function loadFullDefinition($entityClass, $version, RequestType $requestType)
    {
        try {
            $definition = $this->associationHelper->loadDefinition($entityClass, $version, $requestType);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('The configuration for "%s" cannot be loaded.', $entityClass),
                0,
                $e
            );
        }
        if (null === $definition) {
            throw new \RuntimeException(sprintf('The configuration for "%s" was not found.', $entityClass));
        }

        return $definition;
    }

    /**
     * @param string                          $entityClass
     * @param EntityOverrideProviderInterface $entityOverrideProvider
     *
     * @return string
     */
    private function resolveAssociationEntityClass(
        string $entityClass,
        EntityOverrideProviderInterface $entityOverrideProvider
    ): string {
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

    /**
     * @param EntityDefinitionConfig $definition
     * @param string[]|null          $expandedEntities [path, ...]
     *
     * @return array [field name => [path, ...], ...]
     */
    private function getExpandedEntities(EntityDefinitionConfig $definition, $expandedEntities)
    {
        if (empty($expandedEntities)) {
            return [];
        }

        return $this->expandedAssociationExtractor->getFirstLevelOfExpandedAssociations(
            $definition,
            $expandedEntities
        );
    }

    /**
     * @param string $entityClass
     *
     * @return bool
     */
    private function isExtendSystemEntity($entityClass)
    {
        if (!$this->configManager->hasConfig($entityClass)) {
            return false;
        }

        $entityConfig = $this->configManager->getEntityConfig('extend', $entityClass);

        return
            $entityConfig->is('is_extend')
            && !$entityConfig->is('owner', ExtendScope::OWNER_CUSTOM);
    }

    /**
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return bool
     */
    private function isCustomField($entityClass, $fieldName)
    {
        if (!$this->configManager->hasConfig($entityClass, $fieldName)) {
            return false;
        }

        $fieldConfig = $this->configManager->getFieldConfig('extend', $entityClass, $fieldName);

        return
            $fieldConfig->is('is_extend')
            && $fieldConfig->is('owner', ExtendScope::OWNER_CUSTOM);
    }

    /**
     * @param string $entityClass
     * @param string $associationName
     *
     * @return bool
     */
    private function isCustomAssocation($entityClass, $associationName)
    {
        return
            $this->isCustomField($entityClass, $associationName)
            || (
                0 === \strpos($associationName, ExtendConfigDumper::DEFAULT_PREFIX)
                && $this->isCustomField(
                    $entityClass,
                    \substr($associationName, \strlen(ExtendConfigDumper::DEFAULT_PREFIX))
                )
            );
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $propertyPath
     *
     * @return EntityDefinitionFieldConfig|null
     */
    private function getOrAddNotComputedField(
        EntityDefinitionConfig $definition,
        string $propertyPath
    ): ?EntityDefinitionFieldConfig {
        $field = $definition->getField($propertyPath);
        if (null === $field) {
            return $definition->addField($propertyPath);
        }

        if (ConfigUtil::IGNORE_PROPERTY_PATH === $field->getPropertyPath()) {
            return null;
        }

        return $field;
    }
}
