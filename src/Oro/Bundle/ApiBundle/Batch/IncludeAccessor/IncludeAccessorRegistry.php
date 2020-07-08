<?php

namespace Oro\Bundle\ApiBundle\Batch\IncludeAccessor;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Psr\Container\ContainerInterface;

/**
 * The registry that allows to get the included data accessor for a specific request type.
 */
class IncludeAccessorRegistry
{
    /** @var array [[accessor service id, request type expression], ...] */
    private $accessors;

    /** @var ContainerInterface */
    private $container;

    /** @var RequestExpressionMatcher */
    private $matcher;

    /**
     * @param array                    $accessors [[accessor service id, request type expression], ...]
     * @param ContainerInterface       $container
     * @param RequestExpressionMatcher $matcher
     */
    public function __construct(array $accessors, ContainerInterface $container, RequestExpressionMatcher $matcher)
    {
        $this->accessors = $accessors;
        $this->container = $container;
        $this->matcher = $matcher;
    }

    /**
     * Returns the included data accessor for the given request type.
     *
     * @param RequestType $requestType
     *
     * @return IncludeAccessorInterface|null
     */
    public function getAccessor(RequestType $requestType): ?IncludeAccessorInterface
    {
        foreach ($this->accessors as [$serviceId, $expression]) {
            if ($this->matcher->matchValue($expression, $requestType)) {
                return $this->container->get($serviceId);
            }
        }

        return null;
    }
}
