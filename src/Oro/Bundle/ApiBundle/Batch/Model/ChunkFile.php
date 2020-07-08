<?php

namespace Oro\Bundle\ApiBundle\Batch\Model;

/**
 * Represents a chunk file for batch operations.
 */
final class ChunkFile
{
    /** @var string */
    private $fileName;

    /** @var int */
    private $fileIndex;

    /** @var int */
    private $firstRecordOffset;

    /** @var string|null */
    private $sectionName;

    /**
     * @param string      $fileName
     * @param int         $fileIndex
     * @param int         $firstRecordOffset
     * @param string|null $sectionName
     */
    public function __construct(
        string $fileName,
        int $fileIndex,
        int $firstRecordOffset,
        string $sectionName = null
    ) {
        $this->fileName = $fileName;
        $this->fileIndex = $fileIndex;
        $this->firstRecordOffset = $firstRecordOffset;
        $this->sectionName = $sectionName;
    }

    /**
     * Gets the name of the file.
     *
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * Gets the index of the file, starting with zero.
     *
     * @return int
     */
    public function getFileIndex(): int
    {
        return $this->fileIndex;
    }

    /**
     * Gets the offset of the first record in the file, starting with zero.
     * If the source file has several root sections the offset is calculated for each section separately.
     *
     * @return int
     */
    public function getFirstRecordOffset(): int
    {
        return $this->firstRecordOffset;
    }

    /**
     * Gets the name of a section from which this chunk file contains records.
     * The chunk file cannot contains records from different sections.
     *
     * @return string|null
     */
    public function getSectionName(): ?string
    {
        return $this->sectionName;
    }
}
