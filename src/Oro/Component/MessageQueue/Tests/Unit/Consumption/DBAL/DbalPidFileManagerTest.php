<?php
namespace Oro\Component\MessageQueue\Tests\Unit\Consumption\DBAL;

use Oro\Component\MessageQueue\Consumption\Dbal\DbalPidFileManager;
use Oro\Component\Testing\TempDirExtension;
use Symfony\Component\Filesystem\Filesystem;

class DbalPidFileManagerTest extends \PHPUnit\Framework\TestCase
{
    use TempDirExtension;

    /**
     * @var string
     */
    private $pidDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pidDir = $this->getTempDir('test-mq-dbal', false);
    }

    public function testCouldCreatePidFile()
    {
        $expectedFile = $this->pidDir.'/CONSUMER.ID.pid';

        $processManager = new DbalPidFileManager($this->pidDir);
        $processManager->createPidFile('CONSUMER.ID');

        $this->assertFileExists($expectedFile);
        $this->assertTrue(is_numeric(file_get_contents($expectedFile)));
    }

    public function testShouldThrowIfPidFileAlreadyExists()
    {
        $processManager = new DbalPidFileManager($this->pidDir);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The pid file already exists');

        $processManager->createPidFile('CONSUMER.ID');
        $processManager->createPidFile('CONSUMER.ID');
    }

    public function testShouldReturnListOfPidsFileInfo()
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->pidDir.'/pid1.pid', '12345');
        $fs->dumpFile($this->pidDir.'/pid2.pid', '54321');

        $processManager = new DbalPidFileManager($this->pidDir);

        $result = $processManager->getListOfPidsFileInfo();

        $expectedResult = [
            [
                'pid' => 12345,
                'consumerId' => 'pid1',
            ],
            [
                'pid' => 54321,
                'consumerId' => 'pid2',
            ],
        ];

        $this->assertEquals($expectedResult, $result);
    }

    public function testShouldThrowIfPidFileContainsNonNumericValue()
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->pidDir.'/pid1.pid', 'non numeric value');

        $processManager = new DbalPidFileManager($this->pidDir);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected numeric content. content:"non numeric value"');

        $processManager->getListOfPidsFileInfo();
    }

    public function testShouldRemovePidFile()
    {
        $filename = $this->pidDir.'/consumer-id.pid';

        $processManager = new DbalPidFileManager($this->pidDir);
        $processManager->createPidFile('consumer-id');

        // guard
        $this->assertFileExists($filename);

        // test
        $processManager->removePidFile('consumer-id');
        $this->assertFileDoesNotExist($filename);
    }

    public function testShouldNotThrowAnyErrorIfFileDoesNotExistWhenRemovindPids()
    {
        $processManager = new DbalPidFileManager($this->pidDir);
        $processManager->createPidFile('consumer-id');

        // guard
        $this->assertFileDoesNotExist($this->pidDir.'/not-existent-pid-file.pid');

        // test
        $processManager->removePidFile('not-existent-pid-file');
    }
}
