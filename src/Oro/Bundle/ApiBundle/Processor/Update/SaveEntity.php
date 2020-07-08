<?php

namespace Oro\Bundle\ApiBundle\Processor\Update;

use Oro\Bundle\ApiBundle\Processor\SingleItemContext;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Saves all changes of ORM entity to the database.
 */
class SaveEntity implements ProcessorInterface
{
    public const OPERATION_NAME = 'save_existing_entity';

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var SingleItemContext $context */

        if ($context->isProcessed(self::OPERATION_NAME)) {
            // the entity was already saved
            return;
        }

        $entity = $context->getResult();
        if (!\is_object($entity)) {
            // an entity does not exist
            return;
        }

        $em = $this->doctrineHelper->getEntityManager($entity, false);
        if (!$em) {
            // only manageable entities are supported
            return;
        }

        $em->flush();

        $context->setProcessed(self::OPERATION_NAME);
    }
}
