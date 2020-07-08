<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Migration;

use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigMigrationQuery;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Psr\Log\LoggerInterface;

class UpdateEntityConfigMigrationQueryTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $commandExecutor;

    protected function setUp(): void
    {
        $this->commandExecutor = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Tools\CommandExecutor')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetDescription()
    {
        $migrationQuery = new UpdateEntityConfigMigrationQuery(
            $this->commandExecutor
        );

        $this->assertEquals('Update entity configs', $migrationQuery->getDescription());
    }

    public function testExecute()
    {
        $logger = new ArrayLogger();

        $this->commandExecutor
            ->expects($this->once())
            ->method('runCommand')
            ->with(
                'oro:entity-config:update',
                [],
                $logger
            )
            ->will(
                $this->returnCallback(
                    function ($command, $params, $logger) {
                        if ($logger instanceof LoggerInterface) {
                            $logger->info('ok');
                        }

                        return 0;
                    }
                )
            );

        $migrationQuery = new UpdateEntityConfigMigrationQuery($this->commandExecutor);

        $migrationQuery->execute($logger);

        $this->assertEquals(['ok'], $logger->getMessages());
    }
}
