<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Handler;

use Akeneo\Bundle\BatchBundle\Job\Job;
use Oro\Bundle\BatchBundle\Step\ItemStep;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\File\BatchFileManager;
use Oro\Bundle\ImportExportBundle\File\FileManager;
use Oro\Bundle\ImportExportBundle\Handler\AbstractImportHandler;
use Oro\Bundle\ImportExportBundle\Job\JobExecutor;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Bundle\ImportExportBundle\Reader\CsvFileReader;
use Oro\Bundle\ImportExportBundle\Reader\ReaderChain;
use Oro\Bundle\ImportExportBundle\Writer\CsvEchoWriter;
use Oro\Bundle\ImportExportBundle\Writer\WriterChain;
use Symfony\Component\Translation\TranslatorInterface;

abstract class ImportHandlerTestCase extends \PHPUnit\Framework\TestCase
{
    /** @var JobExecutor|\PHPUnit\Framework\MockObject\MockObject */
    protected $jobExecutor;

    /** @var ProcessorRegistry|\PHPUnit\Framework\MockObject\MockObject */
    protected $processorRegistry;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    protected $configProvider;

    /** @var TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $translator;

    /** @var WriterChain|\PHPUnit\Framework\MockObject\MockObject */
    protected $writerChain;

    /** @var ReaderChain|\PHPUnit\Framework\MockObject\MockObject */
    protected $readerChain;

    /** @var BatchFileManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $batchFileManager;

    /** @var FileManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $fileManager;

    protected function setUp()
    {
        $this->jobExecutor = $this->createMock(JobExecutor::class);
        $this->processorRegistry = $this->createMock(ProcessorRegistry::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->writerChain = $this->createMock(WriterChain::class);
        $this->readerChain = $this->createMock(ReaderChain::class);
        $this->batchFileManager = $this->createMock(BatchFileManager::class);
        $this->fileManager = $this->createMock(FileManager::class);
    }

    /**
     * @dataProvider splitImportFileDataProvider
     *
     * @param int|null $batchSize
     * @param array $expectedOptions
     */
    public function testSplitImportFile(?int $batchSize, array $expectedOptions): void
    {
        $jobName = 'entity_import_from_csv';
        $processorType = 'import';

        $writer = new CsvEchoWriter();
        $reader = new CsvFileReader($this->createMock(ContextRegistry::class));

        $step = new ItemStep($processorType);
        $step->setReader($reader);
        $step->setBatchSize($batchSize);

        $job = new Job($jobName);
        $job->addStep($processorType, $step);

        $this->jobExecutor->expects($this->once())
            ->method('getJob')
            ->with($processorType, $jobName)
            ->willReturn($job);

        $this->batchFileManager->expects($this->once())
            ->method('setReader')
            ->with($reader);
        $this->batchFileManager->expects($this->once())
            ->method('setWriter')
            ->with($writer);
        $this->batchFileManager->expects($this->once())
            ->method('setConfigurationOptions')
            ->with($expectedOptions);
        $this->batchFileManager->expects($this->once())
            ->method('splitFile')
            ->with('test_file.csv')
            ->willReturn(['data']);

        $importHandler = $this->getImportHandler();
        $importHandler->setImportingFileName('test_file.csv');

        $this->assertEquals(['data'], $importHandler->splitImportFile($jobName, $processorType, $writer));
    }

    /**
     * @return array
     */
    public function splitImportFileDataProvider(): array
    {
        return [
            'with batch size' => [
                'batchSize' => 300,
                'expectedOptions' => ['batch_size' => 300],
            ],
            'without batch size' => [
                'batchSize' => null,
                'expectedOptions' => [],
            ],
        ];
    }

    /**
     * @return AbstractImportHandler
     */
    abstract protected function getImportHandler(): AbstractImportHandler;
}
