<?php

namespace Oro\Bundle\ApiBundle\ApiDoc;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Psr\Container\ContainerInterface;

/**
 * Joins documentation from all child documentation providers.
 */
class ChainDocumentationProvider implements DocumentationProviderInterface
{
    /** @var array [[provider service id, request type expression], ...] */
    private $providers;

    /** @var ContainerInterface */
    private $container;

    /** @var RequestExpressionMatcher */
    private $matcher;

    /**
     * @param array                    $providers
     * @param ContainerInterface       $container
     * @param RequestExpressionMatcher $matcher
     */
    public function __construct(
        array $providers,
        ContainerInterface $container,
        RequestExpressionMatcher $matcher
    ) {
        $this->providers = $providers;
        $this->container = $container;
        $this->matcher = $matcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getDocumentation(RequestType $requestType): ?string
    {
        $paragraphs = [];
        foreach ($this->providers as list($serviceId, $expression)) {
            if ($this->isMatched($expression, $requestType)) {
                $provider = $this->instantiateProvider($serviceId);
                $documentation = $provider->getDocumentation($requestType);
                if ($documentation) {
                    $paragraphs[] = $documentation;
                }
            }
        }

        if (empty($paragraphs)) {
            return null;
        }

        return \implode("\n\n", $paragraphs);
    }

    /**
     * @param mixed       $expression
     * @param RequestType $requestType
     *
     * @return bool
     */
    private function isMatched($expression, RequestType $requestType): bool
    {
        return !$expression || $this->matcher->matchValue($expression, $requestType);
    }

    /**
     * @param string $serviceId
     *
     * @return DocumentationProviderInterface
     */
    private function instantiateProvider(string $serviceId): DocumentationProviderInterface
    {
        return $this->container->get($serviceId);
    }
}
