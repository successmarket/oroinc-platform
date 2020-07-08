<?php

namespace Oro\Bundle\ApiBundle\Processor\GetConfig;

use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\CompleteEntityDefinitionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\CompleteObjectDefinitionHelper;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Makes sure that identifier field names are set for ORM entities.
 * Updates configuration to ask the EntitySerializer that the entity class should be returned
 * together with related entity data if the entity implemented using Doctrine table inheritance.
 * If "identifier_fields_only" config extra does not exist:
 * * Adds fields and associations which were not configured yet based on an entity metadata.
 * * Marks all not accessible fields and associations as excluded.
 * * The entity exclusion provider is used.
 * * Sets "identifier only" configuration for all associations which were not configured yet.
 * If "identifier_fields_only" config extra exists:
 * * Adds identifier fields which were not configured yet based on an entity metadata.
 * * Removes all other fields and association.
 * Updates configuration of fields if other fields a linked to them using "property_path".
 * Completes configuration of extended associations (associations with data_type=association:...[:...]).
 * Completes configuration of fields that represent nested objects and nested associations.
 * If exclusion policy equals to "custom_fields" and entity is system extend entity
 * ("is_extend" = true and "owner" != "Custom" in "extend" scope in entity configuration)
 * the custom fields (fields with "is_extend" = true and "owner" = "Custom" in "extend" scope in entity configuration)
 * that do not configured explicitly are skipped.
 * Sets "exclusion_policy = all" for the entity. It means that the configuration
 * of all fields and associations was completed.
 * By performance reasons all these actions are done in one processor.
 */
class CompleteDefinition implements ProcessorInterface
{
    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var CompleteEntityDefinitionHelper */
    protected $entityDefinitionHelper;

    /** @var CompleteObjectDefinitionHelper */
    protected $objectDefinitionHelper;

    /**
     * @param DoctrineHelper                 $doctrineHelper
     * @param CompleteEntityDefinitionHelper $entityDefinitionHelper
     * @param CompleteObjectDefinitionHelper $objectDefinitionHelper
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        CompleteEntityDefinitionHelper $entityDefinitionHelper,
        CompleteObjectDefinitionHelper $objectDefinitionHelper
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->entityDefinitionHelper = $entityDefinitionHelper;
        $this->objectDefinitionHelper = $objectDefinitionHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $definition = $context->getResult();
        $context->setRequestedExclusionPolicy($definition->getExclusionPolicy());
        if ($definition->isExcludeAll()) {
            // already processed
            return;
        }

        $entityClass = $context->getClassName();
        if ($this->doctrineHelper->isManageableEntityClass($entityClass)) {
            $this->entityDefinitionHelper->completeDefinition($definition, $context);
        } else {
            $this->objectDefinitionHelper->completeDefinition($definition, $context);
        }

        // mark the entity configuration as processed
        $definition->setExcludeAll();
    }
}
