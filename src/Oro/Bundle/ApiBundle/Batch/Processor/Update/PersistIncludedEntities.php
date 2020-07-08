<?php

namespace Oro\Bundle\ApiBundle\Batch\Processor\Update;

use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\ApiBundle\Processor\FormContext;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Makes all new included entities persistent for all batch items that do not have errors.
 */
class PersistIncludedEntities implements ProcessorInterface
{
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
        /** @var BatchUpdateContext $context */

        $items = $context->getBatchItems();
        if (!$items) {
            return;
        }

        foreach ($items as $item) {
            $itemContext = $item->getContext();
            if (!$itemContext->hasErrors()) {
                $itemTargetContext = $itemContext->getTargetContext();
                if ($itemTargetContext instanceof FormContext) {
                    $this->persistIncludedEntities($itemTargetContext);
                }
            }
        }
    }

    /**
     * @param FormContext $context
     */
    private function persistIncludedEntities(FormContext $context): void
    {
        $additionalEntities = $context->getAdditionalEntities();
        foreach ($additionalEntities as $entity) {
            $this->persistEntity($entity, true);
        }

        $includedEntities = $context->getIncludedEntities();
        if (null !== $includedEntities) {
            foreach ($includedEntities as $entity) {
                if (!$includedEntities->getData($entity)->isExisting()) {
                    $this->persistEntity($entity);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @param bool   $checkIsNew
     */
    private function persistEntity($entity, bool $checkIsNew = false): void
    {
        $em = $this->doctrineHelper->getEntityManager($entity, false);
        if (null === $em) {
            return;
        }

        if ($checkIsNew && UnitOfWork::STATE_NEW !== $em->getUnitOfWork()->getEntityState($entity)) {
            return;
        }

        $em->persist($entity);
    }
}
