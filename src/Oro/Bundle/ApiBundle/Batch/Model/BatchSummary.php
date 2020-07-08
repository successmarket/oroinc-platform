<?php

namespace Oro\Bundle\ApiBundle\Batch\Model;

/**
 * Represents the summary of a batch operation.
 */
final class BatchSummary
{
    /** @var int */
    private $readCount = 0;

    /** @var int */
    private $writeCount = 0;

    /** @var int */
    private $errorCount = 0;

    /** @var int */
    private $createCount = 0;

    /** @var int */
    private $updateCount = 0;

    /**
     * Gets the number of items that have been successfully read.
     *
     * @return int
     */
    public function getReadCount(): int
    {
        return $this->readCount;
    }

    /**
     * Increments the number of items that have been successfully read.
     *
     * @param int $increment
     */
    public function incrementReadCount(int $increment = 1): void
    {
        $this->readCount += $increment;
    }

    /**
     * Gets the number of items that have been successfully written.
     *
     * @return int
     */
    public function getWriteCount(): int
    {
        return $this->writeCount;
    }

    /**
     * Increments the number of items that have been successfully written.
     *
     * @param int $increment
     */
    public function incrementWriteCount(int $increment = 1): void
    {
        $this->writeCount += $increment;
    }

    /**
     * Gets the number of errors occurred when processing this batch operation.
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Increments the number of errors occurred when processing this batch operation.
     *
     * @param int $increment
     */
    public function incrementErrorCount(int $increment = 1): void
    {
        $this->errorCount += $increment;
    }

    /**
     * Gets the number of items that have been successfully created.
     *
     * @return int
     */
    public function getCreateCount(): int
    {
        return $this->createCount;
    }

    /**
     * Increments the number of items that have been successfully created.
     *
     * @param int $increment
     */
    public function incrementCreateCount(int $increment = 1): void
    {
        $this->createCount += $increment;
    }

    /**
     * Gets the number of items that have been successfully updated.
     *
     * @return int
     */
    public function getUpdateCount(): int
    {
        return $this->updateCount;
    }

    /**
     * Increments the number of items that have been successfully updated.
     *
     * @param int $increment
     */
    public function incrementUpdateCount(int $increment = 1): void
    {
        $this->updateCount += $increment;
    }
}
