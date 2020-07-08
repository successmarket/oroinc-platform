<?php

namespace Oro\Bundle\ApiBundle\Batch\Async;

use Oro\Bundle\ApiBundle\Batch\Model\ChunkFile;

/**
 * Represents a class that is used to determine a type of a chunk file.
 */
interface ChunkFileClassifierInterface
{
    /**
     * Checks whether the given chunk file contains primary data.
     *
     * @param ChunkFile $file
     *
     * @return bool
     */
    public function isPrimaryData(ChunkFile $file): bool;

    /**
     * Checks whether the given chunk file contains additional data included into the request.
     *
     * @param ChunkFile $file
     *
     * @return bool
     */
    public function isIncludedData(ChunkFile $file): bool;
}
