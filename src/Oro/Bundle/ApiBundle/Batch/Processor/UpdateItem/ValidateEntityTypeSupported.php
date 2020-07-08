<?php

namespace Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem;

use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Request\Constraint;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Checks if the entity type is supported to be processed by this batch operation.
 */
class ValidateEntityTypeSupported implements ProcessorInterface
{
    /** @var ValueNormalizer */
    private $valueNormalizer;

    /**
     * @param ValueNormalizer $valueNormalizer
     */
    public function __construct(ValueNormalizer $valueNormalizer)
    {
        $this->valueNormalizer = $valueNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var BatchUpdateItemContext $context */

        $supportedEntityClasses = $context->getSupportedEntityClasses();
        if (empty($supportedEntityClasses)) {
            // there are not restriction to entity types that can be processed by this batch operation
            return;
        }

        $entityClass = $context->getClassName();
        if (!$entityClass || false === strpos($entityClass, '\\')) {
            // an entity class does not exist in the context or it is not normalized
            return;
        }

        if (!in_array($entityClass, $supportedEntityClasses, true)) {
            $context->addError(
                Error::createValidationError(
                    Constraint::ENTITY_TYPE,
                    sprintf(
                        'The entity type "%s" is not supported by this batch operation.',
                        $this->getEntityType($entityClass, $context->getRequestType())
                    )
                )
            );
        }
    }

    /**
     * @param string      $entityClass
     * @param RequestType $requestType
     *
     * @return string
     */
    private function getEntityType(string $entityClass, RequestType $requestType): string
    {
        $entityType = ValueNormalizerUtil::convertToEntityType(
            $this->valueNormalizer,
            $entityClass,
            $requestType,
            false
        );
        if (!$entityType) {
            $entityType = $entityClass;
        }

        return $entityType;
    }
}
