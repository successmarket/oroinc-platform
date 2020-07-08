<?php

namespace Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions;

use Oro\Bundle\ApiBundle\ApiDoc\EntityDescriptionProvider;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionFieldConfig;
use Oro\Bundle\ApiBundle\Model\Label;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\InheritDocUtil;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The helper that is used to set descriptions of fields,
 * including special fields such as "createdAt", "updatedAt", "owner", "organizations"
 * and fields for entities based on AbstractEnumValue.
 */
class FieldsDescriptionHelper
{
    private const  CREATED_AT_DESCRIPTION    = 'The date and time of resource record creation.';
    private const  UPDATED_AT_DESCRIPTION    = 'The date and time of the last update of the resource record.';
    private const  OWNER_DESCRIPTION         = 'An owner record represents the ownership capabilities of the record.';
    private const  ORGANIZATION_DESCRIPTION  = 'An organization record represents a real enterprise, business, firm,'
    . ' company or another organization to which the users belong.';
    private const  ENUM_NAME_DESCRIPTION     = 'The human readable name of the option.';
    private const  ENUM_DEFAULT_DESCRIPTION  = 'Determines if this option is selected by default for new records.';
    private const  ENUM_PRIORITY_DESCRIPTION = 'The order in which options are ranked.'
    . ' First appears the option with the higher number of the priority.';

    /** @var EntityDescriptionProvider */
    private $entityDocProvider;

    /** @var TranslatorInterface */
    private $translator;

    /** @var ResourceDocParserProvider */
    private $resourceDocParserProvider;

    /** @var DescriptionProcessor */
    private $descriptionProcessor;

    /** @var IdentifierDescriptionHelper */
    private $identifierDescriptionHelper;

    /** @var ConfigProvider */
    private $ownershipConfigProvider;

    /**
     * @param EntityDescriptionProvider   $entityDocProvider
     * @param TranslatorInterface         $translator
     * @param ResourceDocParserProvider   $resourceDocParserProvider
     * @param DescriptionProcessor        $descriptionProcessor
     * @param IdentifierDescriptionHelper $identifierDescriptionHelper
     * @param ConfigProvider              $ownershipConfigProvider
     */
    public function __construct(
        EntityDescriptionProvider $entityDocProvider,
        TranslatorInterface $translator,
        ResourceDocParserProvider $resourceDocParserProvider,
        DescriptionProcessor $descriptionProcessor,
        IdentifierDescriptionHelper $identifierDescriptionHelper,
        ConfigProvider $ownershipConfigProvider
    ) {
        $this->entityDocProvider = $entityDocProvider;
        $this->translator = $translator;
        $this->resourceDocParserProvider = $resourceDocParserProvider;
        $this->descriptionProcessor = $descriptionProcessor;
        $this->identifierDescriptionHelper = $identifierDescriptionHelper;
        $this->ownershipConfigProvider = $ownershipConfigProvider;
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param RequestType            $requestType
     * @param string                 $entityClass
     * @param bool                   $isInherit
     * @param string                 $targetAction
     * @param string|null            $fieldPrefix
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function setDescriptionsForFields(
        EntityDefinitionConfig $definition,
        RequestType $requestType,
        string $entityClass,
        bool $isInherit,
        string $targetAction,
        string $fieldPrefix = null
    ): void {
        $identifierFieldName = $this->getIdentifierFieldName($definition);
        $fields = $definition->getFields();
        foreach ($fields as $fieldName => $field) {
            if ($isInherit || !$field->hasDescription()) {
                $description = $this->getDescriptionOfField(
                    $field,
                    $requestType,
                    $entityClass,
                    $targetAction,
                    $fieldName,
                    $fieldPrefix,
                    $fieldName === $identifierFieldName ? IdentifierDescriptionHelper::ID_DESCRIPTION : null
                );
                if ($description) {
                    $field->setDescription($description);
                }
            } else {
                $description = $field->getDescription();
                if ($description instanceof Label) {
                    $field->setDescription($this->trans($description));
                } elseif (InheritDocUtil::hasInheritDoc($description)) {
                    $field->setDescription(InheritDocUtil::replaceInheritDoc(
                        $description,
                        $fieldName === $identifierFieldName
                            ? IdentifierDescriptionHelper::ID_DESCRIPTION
                            : $this->getFieldDescription($entityClass, $field, $fieldName, $fieldPrefix)
                    ));
                }
            }

            $description = $field->getDescription();
            if ($description) {
                if (InheritDocUtil::hasDescriptionInheritDoc($description)) {
                    $description = InheritDocUtil::replaceDescriptionInheritDoc(
                        $description,
                        $this->getFieldDescription($entityClass, $field, $fieldName, $fieldPrefix)
                    );
                }
                $field->setDescription($this->descriptionProcessor->process($description, $requestType));
            }

            $targetEntity = $field->getTargetEntity();
            if ($targetEntity && $targetEntity->hasFields()) {
                $targetClass = $field->getTargetClass();
                $targetFieldPrefix = null;
                if (!$targetClass) {
                    $targetFieldPrefix = $this->resolveFieldName($fieldName, $field) . ConfigUtil::PATH_DELIMITER;
                }
                $this->setDescriptionsForFields(
                    $targetEntity,
                    $requestType,
                    $entityClass,
                    $isInherit,
                    $targetAction,
                    $targetFieldPrefix
                );
            }
        }

        $this->identifierDescriptionHelper->setDescriptionForIdentifierField(
            $definition,
            $entityClass,
            $targetAction
        );
        $this->setDescriptionForCreatedAtField($definition, $targetAction);
        $this->setDescriptionForUpdatedAtField($definition, $targetAction);
        $this->setDescriptionsForOwnershipFields($definition, $entityClass);
        if (\is_a($entityClass, AbstractEnumValue::class, true)) {
            $this->setDescriptionsForEnumFields($definition);
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     *
     * @return string
     */
    private function getIdentifierFieldName(EntityDefinitionConfig $definition): ?string
    {
        $identifierFieldNames = $definition->getIdentifierFieldNames();
        if (count($identifierFieldNames) !== 1) {
            return null;
        }

        return $definition->findFieldNameByPropertyPath(reset($identifierFieldNames));
    }

    /**
     * @param string                      $fieldName
     * @param EntityDefinitionFieldConfig $field
     *
     * @return string
     */
    private function resolveFieldName(string $fieldName, EntityDefinitionFieldConfig $field): string
    {
        $propertyPath = $field->getPropertyPath();
        if ($propertyPath && ConfigUtil::IGNORE_PROPERTY_PATH !== $propertyPath) {
            $fieldName = $propertyPath;
        }

        return $fieldName;
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $targetAction
     */
    private function setDescriptionForCreatedAtField(EntityDefinitionConfig $definition, string $targetAction): void
    {
        FieldDescriptionUtil::updateFieldDescription(
            $definition,
            'createdAt',
            self::CREATED_AT_DESCRIPTION
        );
        FieldDescriptionUtil::updateReadOnlyFieldDescription($definition, 'createdAt', $targetAction);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $targetAction
     */
    private function setDescriptionForUpdatedAtField(EntityDefinitionConfig $definition, string $targetAction): void
    {
        FieldDescriptionUtil::updateFieldDescription(
            $definition,
            'updatedAt',
            self::UPDATED_AT_DESCRIPTION
        );
        FieldDescriptionUtil::updateReadOnlyFieldDescription($definition, 'updatedAt', $targetAction);
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $entityClass
     */
    private function setDescriptionsForOwnershipFields(EntityDefinitionConfig $definition, string $entityClass): void
    {
        if (!$this->ownershipConfigProvider->hasConfig($entityClass)) {
            // ownership fields are available only for configurable entities
            return;
        }

        $entityConfig = $this->ownershipConfigProvider->getConfig($entityClass);
        $this->updateOwnershipFieldDescription(
            $definition,
            $entityConfig,
            'owner_field_name',
            self::OWNER_DESCRIPTION
        );
        $this->updateOwnershipFieldDescription(
            $definition,
            $entityConfig,
            'organization_field_name',
            self::ORGANIZATION_DESCRIPTION
        );
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param ConfigInterface        $entityConfig
     * @param string                 $configKey
     * @param string                 $description
     */
    private function updateOwnershipFieldDescription(
        EntityDefinitionConfig $definition,
        ConfigInterface $entityConfig,
        string $configKey,
        string $description
    ): void {
        $propertyPath = $entityConfig->get($configKey);
        if ($propertyPath) {
            $field = $definition->findField($propertyPath, true);
            if (null !== $field) {
                $existingDescription = $field->getDescription();
                if (!$existingDescription) {
                    $field->setDescription($description);
                }
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     */
    private function setDescriptionsForEnumFields(EntityDefinitionConfig $definition): void
    {
        FieldDescriptionUtil::updateFieldDescription(
            $definition,
            'name',
            self::ENUM_NAME_DESCRIPTION
        );
        FieldDescriptionUtil::updateFieldDescription(
            $definition,
            'default',
            self::ENUM_DEFAULT_DESCRIPTION
        );
        FieldDescriptionUtil::updateFieldDescription(
            $definition,
            'priority',
            self::ENUM_PRIORITY_DESCRIPTION
        );
    }

    /**
     * @param EntityDefinitionFieldConfig $field
     * @param RequestType                 $requestType
     * @param string                      $entityClass
     * @param string                      $targetAction
     * @param string                      $fieldName
     * @param string|null                 $fieldPrefix
     * @param string|null                 $fieldDescriptionReplacement
     *
     * @return string|null
     */
    private function getDescriptionOfField(
        EntityDefinitionFieldConfig $field,
        RequestType $requestType,
        string $entityClass,
        string $targetAction,
        string $fieldName,
        ?string $fieldPrefix,
        ?string $fieldDescriptionReplacement
    ): ?string {
        $resourceDocParser = $this->resourceDocParserProvider->getResourceDocParser($requestType);
        $description = $resourceDocParser->getFieldDocumentation($entityClass, $fieldName, $targetAction);
        if ($description) {
            if (InheritDocUtil::hasInheritDoc($description)) {
                $fieldDescription = $fieldDescriptionReplacement;
                if (!$fieldDescription) {
                    $fieldDescription = $this->getFieldDescription($entityClass, $field, $fieldName, $fieldPrefix);
                }
                $commonDescription = $resourceDocParser->getFieldDocumentation($entityClass, $fieldName);
                if ($commonDescription) {
                    if (InheritDocUtil::hasInheritDoc($commonDescription)) {
                        $commonDescription = InheritDocUtil::replaceInheritDoc($commonDescription, $fieldDescription);
                    }
                } else {
                    $commonDescription = $fieldDescription;
                }
                $description = InheritDocUtil::replaceInheritDoc($description, $commonDescription);
            }
        } else {
            $description = $resourceDocParser->getFieldDocumentation($entityClass, $fieldName);
            if ($description) {
                if (InheritDocUtil::hasInheritDoc($description)) {
                    $fieldDescription = $fieldDescriptionReplacement;
                    if (!$fieldDescription) {
                        $fieldDescription = $this->getFieldDescription($entityClass, $field, $fieldName, $fieldPrefix);
                    }
                    $description = InheritDocUtil::replaceInheritDoc($description, $fieldDescription);
                }
            } else {
                $description = $this->getFieldDescription($entityClass, $field, $fieldName, $fieldPrefix);
                if ($description && $fieldDescriptionReplacement) {
                    $description = $fieldDescriptionReplacement;
                }
            }
        }

        return $description;
    }

    /**
     * @param string                      $entityClass
     * @param EntityDefinitionFieldConfig $field
     * @param string                      $fieldName
     * @param string|null                 $fieldPrefix
     *
     * @return string|null
     */
    private function getFieldDescription(
        string $entityClass,
        EntityDefinitionFieldConfig $field,
        string $fieldName,
        ?string $fieldPrefix
    ): ?string {
        $propertyPath = $field->getPropertyPath($fieldName);
        if ($fieldPrefix) {
            $propertyPath = $fieldPrefix . $propertyPath;
        }

        return $this->entityDocProvider->getFieldDocumentation($entityClass, $propertyPath);
    }

    /**
     * @param Label $label
     *
     * @return string|null
     */
    private function trans(Label $label): ?string
    {
        return $label->trans($this->translator) ?: null;
    }
}
