<?php

namespace Oro\Bundle\ApiBundle\Batch\Async;

use Oro\Bundle\ApiBundle\Batch\FileNameProvider;
use Oro\Bundle\ApiBundle\Batch\JsonUtil;
use Oro\Bundle\ApiBundle\Batch\Model\ChunkFile;
use Oro\Bundle\GaufretteBundle\FileManager;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Psr\Log\LoggerInterface;

/**
 * Provides a set of utility methods to simplify working with chunk files related to a batch operation.
 */
class UpdateListProcessingHelper
{
    /** @var FileManager */
    private $fileManager;

    /** @var FileNameProvider */
    private $fileNameProvider;

    /** @var MessageProducerInterface */
    private $producer;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param FileManager              $fileManager
     * @param FileNameProvider         $fileNameProvider
     * @param MessageProducerInterface $producer
     * @param LoggerInterface          $logger
     */
    public function __construct(
        FileManager $fileManager,
        FileNameProvider $fileNameProvider,
        MessageProducerInterface $producer,
        LoggerInterface $logger
    ) {
        $this->fileManager = $fileManager;
        $this->fileNameProvider = $fileNameProvider;
        $this->producer = $producer;
        $this->logger = $logger;
    }

    /**
     * @param array $parentBody
     *
     * @return array
     */
    public function getCommonBody(array $parentBody): array
    {
        return array_intersect_key(
            $parentBody,
            array_flip(['operationId', 'entityClass', 'requestType', 'version'])
        );
    }

    /**
     * @param float $startTimestamp
     * @param int   $additionalAggregateTime
     *
     * @return int
     */
    public function calculateAggregateTime(float $startTimestamp, int $additionalAggregateTime = 0): int
    {
        return (int)round(1000 * (microtime(true) - $startTimestamp)) + $additionalAggregateTime;
    }

    /**
     * @param string $fileName
     */
    public function safeDeleteFile(string $fileName): void
    {
        try {
            $this->fileManager->deleteFile($fileName);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('The deletion of the file "%s" failed.', $fileName),
                ['exception' => $e]
            );
        }
    }

    /**
     * @param int    $operationId
     * @param string $chunkFileNameTemplate
     */
    public function safeDeleteChunkFiles(int $operationId, string $chunkFileNameTemplate): void
    {
        $fileNames = [];
        try {
            $fileNames = $this->fileManager->findFiles(sprintf($chunkFileNameTemplate, ''));
        } catch (\Exception $e) {
            // ignore any exception occurred when deletion chunk files
            $this->logger->error(
                'The finding of chunk files failed.',
                ['operationId' => $operationId, 'exception' => $e]
            );
        }

        foreach ($fileNames as $fileName) {
            $this->safeDeleteFile($fileName);
        }
    }

    /**
     * @param int $operationId
     *
     * @return bool
     */
    public function hasChunkIndex(int $operationId): bool
    {
        return $this->fileManager->hasFile(
            $this->fileNameProvider->getChunkIndexFileName($operationId)
        );
    }

    /**
     * @param int $operationId
     *
     * @return int
     */
    public function getChunkIndexCount(int $operationId): int
    {
        $indexFileName = $this->fileNameProvider->getChunkIndexFileName($operationId);

        return count(JsonUtil::decode($this->fileManager->getFileContent($indexFileName)));
    }

    /**
     * @param int $operationId
     *
     * @return ChunkFile[]
     */
    public function loadChunkIndex(int $operationId): array
    {
        $files = [];
        $indexFileName = $this->fileNameProvider->getChunkIndexFileName($operationId);
        $indexFileContent = $this->fileManager->getFileContent($indexFileName);
        $data = JsonUtil::decode($indexFileContent);
        foreach ($data as [$fileName, $fileIndex, $firstRecordOffset, $sectionName]) {
            $files[] = new ChunkFile($fileName, $fileIndex, $firstRecordOffset, $sectionName);
        }

        return $files;
    }

    /**
     * @param int         $operationId
     * @param ChunkFile[] $files
     */
    public function updateChunkIndex(int $operationId, array $files): void
    {
        $indexFileName = $this->fileNameProvider->getChunkIndexFileName($operationId);
        $indexFile = $this->fileManager->getFile($indexFileName, false);
        $data = null === $indexFile
            ? []
            : JsonUtil::decode($indexFile->getContent());
        foreach ($files as $file) {
            $data[] = [
                $file->getFileName(),
                $file->getFileIndex(),
                $file->getFirstRecordOffset(),
                $file->getSectionName()
            ];
        }
        $this->fileManager->writeToStorage(JsonUtil::encode($data), $indexFileName);
    }

    /**
     * @param int $operationId
     */
    public function deleteChunkIndex(int $operationId): void
    {
        $this->safeDeleteFile($this->fileNameProvider->getChunkIndexFileName($operationId));
    }

    /**
     * @param int $operationId
     *
     * @return int[] [chunk file index => job id, ...]
     */
    public function loadChunkJobIndex(int $operationId): array
    {
        $indexFileName = $this->fileNameProvider->getChunkJobIndexFileName($operationId);

        return JsonUtil::decode($this->fileManager->getFileContent($indexFileName));
    }

    /**
     * @param int   $operationId
     * @param int[] $chunkFileToJobIdMap [chunk file index => job id, ...]
     */
    public function updateChunkJobIndex(int $operationId, array $chunkFileToJobIdMap): void
    {
        $indexFileName = $this->fileNameProvider->getChunkJobIndexFileName($operationId);
        $indexFile = $this->fileManager->getFile($indexFileName, false);
        $data = null === $indexFile
            ? []
            : JsonUtil::decode($indexFile->getContent());
        foreach ($chunkFileToJobIdMap as $chunkFileIndex => $jobId) {
            $data[$chunkFileIndex] = $jobId;
        }
        $this->fileManager->writeToStorage(JsonUtil::encode($data), $indexFileName);
    }

    /**
     * @param int $operationId
     */
    public function deleteChunkJobIndex(int $operationId): void
    {
        $this->safeDeleteFile($this->fileNameProvider->getChunkJobIndexFileName($operationId));
    }

    /**
     * @param JobRunner $jobRunner
     * @param int       $operationId
     * @param string    $chunkJobNameTemplate
     * @param int       $firstChunkFileIndex
     * @param int       $lastChunkFileIndex
     *
     * @return int
     */
    public function createChunkJobs(
        JobRunner $jobRunner,
        int $operationId,
        string $chunkJobNameTemplate,
        int $firstChunkFileIndex,
        int $lastChunkFileIndex
    ): int {
        $chunkFileToJobIdMap = [];
        $fileIndex = $firstChunkFileIndex;
        while ($fileIndex <= $lastChunkFileIndex) {
            $jobRunner->createDelayed(
                sprintf($chunkJobNameTemplate, $fileIndex + 1),
                function (JobRunner $jobRunner, Job $job) use ($fileIndex, &$chunkFileToJobIdMap) {
                    $chunkFileToJobIdMap[$fileIndex] = $job->getId();

                    return true;
                }
            );
            $fileIndex++;
        }
        $this->updateChunkJobIndex($operationId, $chunkFileToJobIdMap);

        return $fileIndex;
    }

    /**
     * @param Job      $rootJob
     * @param string   $chunkJobNameTemplate
     * @param array    $parentBody
     * @param int      $firstChunkFileIndex
     * @param int|null $previousAggregateTime
     */
    public function sendMessageToCreateChunkJobs(
        Job $rootJob,
        string $chunkJobNameTemplate,
        array $parentBody,
        int $firstChunkFileIndex = 0,
        int $previousAggregateTime = null
    ): void {
        $body = array_merge($this->getCommonBody($parentBody), [
            'rootJobId'            => $rootJob->getId(),
            'chunkJobNameTemplate' => $chunkJobNameTemplate
        ]);
        if ($firstChunkFileIndex > 0) {
            $body['firstChunkFileIndex'] = $firstChunkFileIndex;
        }
        if (null !== $previousAggregateTime) {
            $body['aggregateTime'] = $previousAggregateTime;
        }
        $this->producer->send(Topics::UPDATE_LIST_CREATE_CHUNK_JOBS, $body);
    }

    /**
     * @param Job      $rootJob
     * @param array    $parentBody
     * @param int      $firstChunkFileIndex
     * @param int|null $previousAggregateTime
     */
    public function sendMessageToStartChunkJobs(
        Job $rootJob,
        array $parentBody,
        int $firstChunkFileIndex = 0,
        int $previousAggregateTime = null
    ): void {
        $body = array_merge($this->getCommonBody($parentBody), [
            'rootJobId' => $rootJob->getId()
        ]);
        if ($firstChunkFileIndex > 0) {
            $body['firstChunkFileIndex'] = $firstChunkFileIndex;
        }
        if (null !== $previousAggregateTime) {
            $body['aggregateTime'] = $previousAggregateTime;
        }
        $this->producer->send(Topics::UPDATE_LIST_START_CHUNK_JOBS, $body);
    }

    /**
     * @param array     $parentBody
     * @param Job       $job
     * @param ChunkFile $chunkFile
     * @param bool      $extraChunk
     */
    public function sendProcessChunkMessage(
        array $parentBody,
        Job $job,
        ChunkFile $chunkFile,
        bool $extraChunk = false
    ): void {
        $body = array_merge($this->getCommonBody($parentBody), [
            'jobId'             => $job->getId(),
            'fileName'          => $chunkFile->getFileName(),
            'fileIndex'         => $chunkFile->getFileIndex(),
            'firstRecordOffset' => $chunkFile->getFirstRecordOffset(),
            'sectionName'       => $chunkFile->getSectionName()
        ]);
        if ($extraChunk) {
            $body['extra_chunk'] = true;
        }
        $this->producer->send(Topics::UPDATE_LIST_PROCESS_CHUNK, $body);
    }
}
