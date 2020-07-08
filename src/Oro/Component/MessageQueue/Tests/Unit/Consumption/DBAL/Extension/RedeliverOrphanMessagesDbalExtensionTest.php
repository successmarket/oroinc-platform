<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Consumption\DBAL\Extension;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Oro\Component\MessageQueue\Consumption\Context;
use Oro\Component\MessageQueue\Consumption\Dbal\DbalCliProcessManager;
use Oro\Component\MessageQueue\Consumption\Dbal\DbalPidFileManager;
use Oro\Component\MessageQueue\Consumption\Dbal\Extension\RedeliverOrphanMessagesDbalExtension;
use Oro\Component\MessageQueue\Transport\Dbal\DbalConnection;
use Oro\Component\MessageQueue\Transport\Dbal\DbalMessageConsumer;
use Oro\Component\MessageQueue\Transport\Dbal\DbalSessionInterface;
use Psr\Log\LoggerInterface;

class RedeliverOrphanMessagesDbalExtensionTest extends \PHPUnit\Framework\TestCase
{
    public function testCouldBeConstructedWithoutAnyArgument()
    {
        new RedeliverOrphanMessagesDbalExtension(
            $this->createDbalPidFileManagerMock(),
            $this->createDbalCliProcessManagerMock(),
            ':console'
        );
    }

    public function testShouldCreatePidFileOnlyOnce()
    {
        $consumer = $this->createDbalMessageConsumerMock();
        $consumer
            ->expects($this->once())
            ->method('getConsumerId')
            ->will($this->returnValue('consumer-id'))
        ;

        $session = $this->createSessionMock();

        $pidFileManager = $this->createDbalPidFileManagerMock();
        $pidFileManager
            ->expects($this->once())
            ->method('createPidFile')
            ->with('consumer-id')
        ;
        $pidFileManager
            ->expects($this->once())
            ->method('getListOfPidsFileInfo')
            ->will($this->returnValue([]))
        ;

        $context = new Context($session);
        $context->setMessageConsumer($consumer);

        $extension = new RedeliverOrphanMessagesDbalExtension(
            $pidFileManager,
            $this->createDbalCliProcessManagerMock(),
            ':console'
        );

        $extension->onBeforeReceive($context);
        $extension->onBeforeReceive($context);
    }

    public function testShouldRedeliverOrphanMessages()
    {
        $dbalConnection = $this->createDBALConnection();
        $dbalConnection
            ->expects($this->once())
            ->method('executeUpdate')
            ->with(
                'UPDATE  SET consumer_id=NULL, redelivered=:isRedelivered '.
                'WHERE consumer_id IN (:consumerIds)',
                [
                    'isRedelivered' => true,
                    'consumerIds' => ['consumer-id-1', 'consumer-id-2'],
                ],
                [
                    'isRedelivered' => Types::BOOLEAN,
                    'consumerIds' => Connection::PARAM_STR_ARRAY,
                ]
            )
        ;

        $connection = $this->createConnectionMock();
        $connection
            ->expects($this->once())
            ->method('getDBALConnection')
            ->will($this->returnValue($dbalConnection))
        ;

        $session = $this->createSessionMock();
        $session
            ->expects($this->once())
            ->method('getConnection')
            ->will($this->returnValue($connection))
        ;

        $pidFileManager = $this->createDbalPidFileManagerMock();
        $pidFileManager
            ->expects($this->once())
            ->method('getListOfPidsFileInfo')
            ->will($this->returnValue([
                ['pid' => 123, 'consumerId' => 'consumer-id-1'],
                ['pid' => 456, 'consumerId' => 'consumer-id-2'],
            ]))
        ;
        $pidFileManager
            ->expects($this->at(2))
            ->method('removePidFile')
            ->with('consumer-id-1')
        ;
        $pidFileManager
            ->expects($this->at(3))
            ->method('removePidFile')
            ->with('consumer-id-2')
        ;

        $cliProcessManager = $this->createDbalCliProcessManagerMock();
        $cliProcessManager
            ->expects($this->once())
            ->method('getListOfProcessesPids')
            ->will($this->returnValue([]))
        ;

        $logger = $this->createLoggerMock();
        $logger
            ->expects($this->once())
            ->method('critical')
            ->with('Orphans were found and redelivered. consumerIds: "consumer-id-1, consumer-id-2"')
        ;

        $context = new Context($session);
        $context->setMessageConsumer($this->createDbalMessageConsumerMock());
        $context->setLogger($logger);

        $extension = new RedeliverOrphanMessagesDbalExtension(
            $pidFileManager,
            $cliProcessManager,
            ':console'
        );

        $extension->onBeforeReceive($context);
        $extension->onBeforeReceive($context);
    }

    public function testOnInterruptedShouldRemovePidFile()
    {
        $consumer = $this->createDbalMessageConsumerMock();
        $consumer
            ->expects($this->once())
            ->method('getConsumerId')
            ->will($this->returnValue('consumer-id'))
        ;

        $session = $this->createSessionMock();

        $context = new Context($session);
        $context->setMessageConsumer($consumer);

        $pidFileManager = $this->createDbalPidFileManagerMock();
        $pidFileManager
            ->expects($this->once())
            ->method('removePidFile')
            ->with('consumer-id')
        ;

        $extension = new RedeliverOrphanMessagesDbalExtension(
            $pidFileManager,
            $this->createDbalCliProcessManagerMock(),
            ':console'
        );

        $extension->onInterrupted($context);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private function createLoggerMock()
    {
        return $this->createMock(LoggerInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalSessionInterface
     */
    private function createSessionMock()
    {
        return $this->createMock(DbalSessionInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalConnection
     */
    private function createConnectionMock()
    {
        return $this->createMock(DbalConnection::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|Connection
     */
    private function createDBALConnection()
    {
        return $this->createMock(Connection::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalMessageConsumer
     */
    private function createDbalMessageConsumerMock()
    {
        return $this->createMock(DbalMessageConsumer::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalPidFileManager
     */
    private function createDbalPidFileManagerMock()
    {
        return $this->createMock(DbalPidFileManager::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DbalCliProcessManager
     */
    private function createDbalCliProcessManagerMock()
    {
        return $this->createMock(DbalCliProcessManager::class);
    }
}
