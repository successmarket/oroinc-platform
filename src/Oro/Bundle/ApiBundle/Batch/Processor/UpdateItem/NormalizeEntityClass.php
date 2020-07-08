<?php

namespace Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem;

use Oro\Bundle\ApiBundle\Exception\ResourceNotAccessibleException;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Request\Constraint;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Checks if the entity type exists in the context, and if so,
 * converts it to FQCN of an entity and checks that this entity
 * is accessible through API.
 */
class NormalizeEntityClass implements ProcessorInterface
{
    /** @var ValueNormalizer */
    private $valueNormalizer;

    /** @var ResourcesProvider */
    private $resourcesProvider;

    /**
     * @param ValueNormalizer   $valueNormalizer
     * @param ResourcesProvider $resourcesProvider
     */
    public function __construct(ValueNormalizer $valueNormalizer, ResourcesProvider $resourcesProvider)
    {
        $this->valueNormalizer = $valueNormalizer;
        $this->resourcesProvider = $resourcesProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var BatchUpdateItemContext $context */

        $entityClass = $context->getClassName();
        if (!$entityClass) {
            $context->addError(Error::createValidationError(
                Constraint::ENTITY_TYPE,
                'The entity class must be set in the context.'
            ));

            return;
        }

        if (false !== strpos($entityClass, '\\')) {
            // the entity class is already normalized
            return;
        }

        $normalizedEntityClass = $this->getEntityClass(
            $entityClass,
            $context->getVersion(),
            $context->getRequestType()
        );
        $context->setClassName($normalizedEntityClass);
        if (null === $normalizedEntityClass) {
            $context->addError(Error::createValidationError(
                Constraint::ENTITY_TYPE,
                sprintf('Unknown entity type: %s.', $entityClass)
            ));
        }
    }

    /**
     * @param string      $entityType
     * @param string      $version
     * @param RequestType $requestType
     *
     * @return string|null
     */
    private function getEntityClass(string $entityType, string $version, RequestType $requestType): ?string
    {
        $entityClass = ValueNormalizerUtil::convertToEntityClass(
            $this->valueNormalizer,
            $entityType,
            $requestType,
            false
        );
        if (!$entityClass) {
            return null;
        }
        if (!$this->resourcesProvider->isResourceAccessible($entityClass, $version, $requestType)) {
            throw new ResourceNotAccessibleException();
        }

        return $entityClass;
    }
}
