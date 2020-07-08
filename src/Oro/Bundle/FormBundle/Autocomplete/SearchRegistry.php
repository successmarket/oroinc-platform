<?php

namespace Oro\Bundle\FormBundle\Autocomplete;

use Psr\Container\ContainerInterface;

/**
 * The registry of autocomplete search handlers.
 */
class SearchRegistry
{
    /** @var ContainerInterface */
    private $searchHandlers;

    /**
     * @param ContainerInterface $searchHandlers
     */
    public function __construct(ContainerInterface $searchHandlers)
    {
        $this->searchHandlers = $searchHandlers;
    }

    /**
     * @param string $name
     *
     * @return SearchHandlerInterface
     *
     * @throws \RuntimeException if a handler with the given name does not exist
     */
    public function getSearchHandler(string $name): SearchHandlerInterface
    {
        if (!$this->searchHandlers->has($name)) {
            throw new \RuntimeException(sprintf('Search handler "%s" is not registered', $name));
        }

        return $this->searchHandlers->get($name);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasSearchHandler(string $name): bool
    {
        return $this->searchHandlers->has($name);
    }
}
