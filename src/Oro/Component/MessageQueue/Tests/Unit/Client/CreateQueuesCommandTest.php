<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Client;

use Oro\Component\MessageQueue\Client\CreateQueuesCommand;
use Oro\Component\MessageQueue\Client\DriverInterface;
use Oro\Component\MessageQueue\Client\Meta\DestinationMeta;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Symfony\Component\Console\Tester\CommandTester;

class CreateQueuesCommandTest extends \PHPUnit\Framework\TestCase
{
    /** @var CreateQueuesCommand */
    private $command;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $registry;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $driver;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DestinationMetaRegistry::class);
        $this->driver = $this->createMock(DriverInterface::class);

        $this->command = new CreateQueuesCommand($this->driver, $this->registry);
    }

    public function testShouldHaveCommandName()
    {
        $this->assertEquals('oro:message-queue:create-queues', $this->command->getName());
    }

    public function testShouldCreateQueues()
    {
        $destinationMeta1 = new DestinationMeta('', 'queue1');
        $destinationMeta2 = new DestinationMeta('', 'queue2');

        $this->registry->expects($this->once())
            ->method('getDestinationsMeta')
            ->will($this->returnValue([$destinationMeta1, $destinationMeta2]));

        $this->driver->expects($this->at(0))
            ->method('createQueue')
            ->with('queue1');
        $this->driver->expects($this->at(1))
            ->method('createQueue')
            ->with('queue2');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        static::assertStringContainsString('Creating queue: queue1', $tester->getDisplay());
        static::assertStringContainsString('Creating queue: queue2', $tester->getDisplay());
    }
}
