<?php

namespace Oro\Bundle\ApiBundle\Batch\Splitter;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Psr\Container\ContainerInterface;

/**
 * The registry that allows to get the file splitter for a specific request type.
 */
class FileSplitterRegistry
{
    /** @var array [[splitter service id, request type expression], ...] */
    private $splitters;

    /** @var ContainerInterface */
    private $container;

    /** @var RequestExpressionMatcher */
    private $matcher;

    /**
     * @param array                    $splitters [[splitter service id, request type expression], ...]
     * @param ContainerInterface       $container
     * @param RequestExpressionMatcher $matcher
     */
    public function __construct(array $splitters, ContainerInterface $container, RequestExpressionMatcher $matcher)
    {
        $this->splitters = $splitters;
        $this->container = $container;
        $this->matcher = $matcher;
    }

    /**
     * Returns the file splitter for the given request type.
     *
     * @param RequestType $requestType
     *
     * @return FileSplitterInterface|null
     */
    public function getSplitter(RequestType $requestType): ?FileSplitterInterface
    {
        foreach ($this->splitters as list($serviceId, $expression)) {
            if ($this->matcher->matchValue($expression, $requestType)) {
                return $this->container->get($serviceId);
            }
        }

        return null;
    }
}
