<?php

namespace Oro\Bundle\ApiBundle\Processor\Subresource\Shared;

use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeRelationshipContext;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Converts the parent ORM entity to a model and adds the model to the context instead of the entity.
 */
class ConvertParentEntityToModel implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ChangeRelationshipContext $context */

        $parentEntity = $context->getParentEntity();
        if (!\is_object($parentEntity)) {
            // an entity does not exist
            return;
        }

        $entityMapper = $context->getEntityMapper();
        if (null === $entityMapper) {
            // the entity mapper was not initialized
            return;
        }

        $context->setParentEntity(
            $entityMapper->getModel($parentEntity, $context->getParentClassName())
        );
    }
}
