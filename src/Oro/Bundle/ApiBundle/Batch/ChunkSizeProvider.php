<?php

namespace Oro\Bundle\ApiBundle\Batch;

/**
 * Provides a way to get the maximum number of objects that can be saved in a batch operation chunk.
 */
class ChunkSizeProvider
{
    /** @var int */
    private $defaultChunkSize;

    /** @var int[] [entity class => chunk size, ...] */
    private $entityChunkSizes;

    /** @var int */
    private $defaultIncludedDataChunkSize;

    /** @var int[] [entity class => chunk size, ...] */
    private $entityIncludedDataChunkSizes;

    /**
     * @param int   $defaultChunkSize
     * @param int[] $entityChunkSizes
     * @param int   $defaultIncludedDataChunkSize
     * @param int[] $entityIncludedDataChunkSizes
     */
    public function __construct(
        int $defaultChunkSize,
        array $entityChunkSizes,
        int $defaultIncludedDataChunkSize,
        array $entityIncludedDataChunkSizes
    ) {
        $this->defaultChunkSize = $defaultChunkSize;
        $this->entityChunkSizes = $entityChunkSizes;
        $this->defaultIncludedDataChunkSize = $defaultIncludedDataChunkSize;
        $this->entityIncludedDataChunkSizes = $entityIncludedDataChunkSizes;
    }

    /**
     * Gets the maximum number of objects that can be saved in a batch operation chunk.
     *
     * @param string $entityClass
     *
     * @return int
     */
    public function getChunkSize(string $entityClass): int
    {
        return $this->entityChunkSizes[$entityClass] ?? $this->defaultChunkSize;
    }

    /**
     * Gets the maximum number of included objects that can be saved in a batch operation chunk.
     *
     * @param string $entityClass
     *
     * @return int
     */
    public function getIncludedDataChunkSize(string $entityClass): int
    {
        return $this->entityIncludedDataChunkSizes[$entityClass] ?? $this->defaultIncludedDataChunkSize;
    }
}
