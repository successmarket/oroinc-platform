<?php
namespace Oro\Component\MessageQueue\Tests\Unit\Consumption;

use Oro\Component\MessageQueue\Consumption\ChainExtension;
use Oro\Component\MessageQueue\Consumption\Context;
use Oro\Component\MessageQueue\Consumption\ExtensionInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Consumption\QueueConsumer;
use Oro\Component\MessageQueue\Exception\StaleJobRuntimeException;
use Oro\Component\MessageQueue\Log\ConsumerState;
use Oro\Component\MessageQueue\Tests\Unit\Consumption\Mock\BreakCycleExtension;
use Oro\Component\MessageQueue\Transport\ConnectionInterface;
use Oro\Component\MessageQueue\Transport\MessageConsumerInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\QueueInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\NullLogger;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class QueueConsumerTest extends \PHPUnit\Framework\TestCase
{
    public function testCouldBeConstructedWithConnectionAndSingleExtension()
    {
        new QueueConsumer(
            $this->createConnectionStub(),
            $this->createExtension(),
            $this->createConsumerState()
        );
        $this->expectNotToPerformAssertions();
    }

    public function testShouldSetEmptyArrayToBoundMessageProcessorsPropertyInConstructor()
    {
        static::markTestIncomplete('Consumer without any bound processors will run forever, which looks likes a bug.');
        $consumer = new QueueConsumer(
            $this->createConnectionStub($this->createMock(SessionInterface::class)),
            $this->createExtension(),
            $this->createConsumerState(),
            0
        );

        // set expectations after the bug is fixed
        // $consumer->consume();
    }

    public function testShouldAllowGetConnectionSetInConstructor()
    {
        $expectedConnection = $this->createConnectionStub();

        $consumer = new QueueConsumer(
            $expectedConnection,
            $this->createExtension(),
            $this->createConsumerState(),
            0
        );

        $this->assertSame($expectedConnection, $consumer->getConnection());
    }

    public function testThrowIfQueueNameEmptyOnBind()
    {
        $messageProcessorMock = $this->createMessageProcessorMock();

        $consumer = new QueueConsumer(
            $this->createConnectionStub(),
            $this->createExtension(),
            $this->createConsumerState(),
            0
        );
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The queue name must be not empty.');
        $consumer->bind('', $messageProcessorMock);
    }

    public function testThrowIfQueueAlreadyBoundToMessageProcessorOnBind()
    {
        $messageProcessorMock = $this->createMessageProcessorMock();

        $consumer = new QueueConsumer(
            $this->createConnectionStub(),
            $this->createExtension(),
            $this->createConsumerState(),
            0
        );

        $consumer->bind('theQueueName', $messageProcessorMock);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The queue was already bound.');
        $consumer->bind('theQueueName', $messageProcessorMock);
    }

    public function testShouldReturnSelfOnBind()
    {
        $messageProcessorMock = $this->createMessageProcessorMock();

        $consumer = new QueueConsumer(
            $this->createConnectionStub(),
            $this->createExtension(),
            $this->createConsumerState(),
            0
        );

        $this->assertSame($consumer, $consumer->bind('aQueueName', $messageProcessorMock));
    }

    public function testShouldSubscribeToGivenQueueAndQuitAfterFifthIdleCycle()
    {
        $expectedQueueName = 'theQueueName';
        $expectedQueue = $this->createMock(QueueInterface::class);

        $messageConsumerMock = $this->createMock(MessageConsumerInterface::class);
        $messageConsumerMock
            ->expects($this->exactly(5))
            ->method('receive')
            ->willReturn(null)
        ;

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($expectedQueue))
            ->willReturn($messageConsumerMock)
        ;
        $sessionMock
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($expectedQueue)
        ;

        $connectionStub = $this->createConnectionStub($sessionMock);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->never())
            ->method('process')
        ;

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(5),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind($expectedQueueName, $messageProcessorMock);
        $queueConsumer->consume();
    }

    public function testShouldProcessFiveMessagesAndQuit()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->exactly(5))
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(5),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testThrowIfProcessorThrowsStaleException()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->exactly(1))
            ->method('process')
            ->willThrowException(StaleJobRuntimeException::create())
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $this->expectException(StaleJobRuntimeException::class);
        $this->expectExceptionMessage('Stale Jobs cannot be run');

        $queueConsumer->consume();
    }

    public function testShouldAckMessageIfMessageProcessorReturnSuchStatus()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);
        $messageConsumerStub
            ->expects($this->once())
            ->method('acknowledge')
            ->with($this->identicalTo($messageMock))
        ;

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->with($this->identicalTo($messageMock))
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testThrowIfMessageProcessorReturnNull()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->with($this->identicalTo($messageMock))
            ->willReturn(null)
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Status is not supported');
        $queueConsumer->consume();
    }

    public function testShouldRejectMessageIfMessageProcessorReturnSuchStatus()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);
        $messageConsumerStub
            ->expects($this->once())
            ->method('reject')
            ->with($this->identicalTo($messageMock), false)
        ;

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->with($this->identicalTo($messageMock))
            ->willReturn(MessageProcessorInterface::REJECT)
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldRequeueMessageIfMessageProcessorReturnSuchStatus()
    {
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);
        $messageConsumerStub
            ->expects($this->once())
            ->method('reject')
            ->with($this->identicalTo($messageMock), true)
        ;

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->with($this->identicalTo($messageMock))
            ->willReturn(MessageProcessorInterface::REQUEUE)
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testThrowIfMessageProcessorReturnInvalidStatus()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Status is not supported: invalidStatus');
        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->with($this->identicalTo($messageMock))
            ->willReturn('invalidStatus')
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldNotPassMessageToMessageProcessorIfItWasProcessedByExtension()
    {
        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onPreReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) {
                $context->setStatus(MessageProcessorInterface::ACK);
            })
        ;

        $messageMock = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($messageMock);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->never())
            ->method('process')
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallOnStartExtensionMethod()
    {
        $messageConsumerStub = $this->createMessageConsumerStub($message = null);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onStart')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertNull($context->getMessageConsumer());
                $this->assertNull($context->getMessageProcessor());
                $this->assertNull($context->getLogger());
                $this->assertNull($context->getMessage());
                $this->assertNull($context->getException());
                $this->assertNull($context->getStatus());
                $this->assertNull($context->getQueueName());
                $this->assertFalse($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallOnIdleExtensionMethod()
    {
        $messageConsumerStub = $this->createMessageConsumerStub($message = null);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onIdle')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getMessage());
                $this->assertNull($context->getException());
                $this->assertNull($context->getStatus());
                $this->assertFalse($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallOnBeforeReceiveExtensionMethod()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorStub();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onBeforeReceive')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getMessage());
                $this->assertNull($context->getException());
                $this->assertNull($context->getStatus());
                $this->assertFalse($context->isExecutionInterrupted());
                $this->assertEquals('theQueueName', $context->getQueueName());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('theQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallOnPreReceivedAndPostReceivedExtensionMethods()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorStub();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onPreReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertSame($expectedMessage, $context->getMessage());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getException());
                $this->assertNull($context->getStatus());
                $this->assertFalse($context->isExecutionInterrupted());
            })
        ;
        $extension
            ->expects($this->once())
            ->method('onPostReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertSame($expectedMessage, $context->getMessage());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getException());
                $this->assertSame(MessageProcessorInterface::ACK, $context->getStatus());
                $this->assertFalse($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldAllowInterruptConsumingOnIdle()
    {
        $messageConsumerStub = $this->createMessageConsumerStub($message = null);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onIdle')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) {
                $context->setExecutionInterrupted(true);
            })
        ;
        $extension
            ->expects($this->once())
            ->method('onInterrupted')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getMessage());
                $this->assertNull($context->getException());
                $this->assertNull($context->getStatus());
                $this->assertTrue($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCloseSessionWhenConsumptionInterrupted()
    {
        $messageConsumerStub = $this->createMessageConsumerStub($message = null);

        $sessionMock = $this->createSessionStub($messageConsumerStub);
        $sessionMock
            ->expects($this->once())
            ->method('close')
        ;

        $messageProcessorMock = $this->createMessageProcessorMock();

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onIdle')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) {
                $context->setExecutionInterrupted(true);
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionMock);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCloseSessionWhenConsumptionInterruptedByException()
    {
        $expectedException = new \Exception;

        $messageConsumerStub = $this->createMessageConsumerStub($message = $this->createMessageMock());

        $sessionMock = $this->createSessionStub($messageConsumerStub);
        $sessionMock
            ->expects($this->once())
            ->method('close')
        ;

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willThrowException($expectedException)
        ;

        $connectionStub = $this->createConnectionStub($sessionMock);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        try {
            $queueConsumer->consume();
        } catch (\Exception $e) {
            $this->assertSame($expectedException, $e);
            $this->assertNull($e->getPrevious());

            return;
        }

        $this->fail('Exception throw is expected.');
    }

    public function testShouldSetMainExceptionAsPreviousToExceptionThrownOnInterrupt()
    {
        $mainException = new \Exception;
        $expectedException = new \Exception;

        $messageConsumerStub = $this->createMessageConsumerStub($message = $this->createMessageMock());

        $sessionMock = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willThrowException($mainException)
        ;

        $extension = $this->createExtension();
        $extension
            ->expects($this->atLeastOnce())
            ->method('onInterrupted')
            ->willThrowException($expectedException)
        ;

        $connectionStub = $this->createConnectionStub($sessionMock);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        try {
            $queueConsumer->consume();
        } catch (\Exception $e) {
            $this->assertSame($expectedException, $e);
            $this->assertSame($mainException, $e->getPrevious());

            return;
        }

        $this->fail('Exception throw is expected.');
    }

    public function testShouldAllowInterruptConsumingOnPreReceiveButProcessCurrentMessage()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onPreReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) {
                $context->setExecutionInterrupted(true);
            })
        ;
        $extension
            ->expects($this->atLeastOnce())
            ->method('onInterrupted')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertSame($expectedMessage, $context->getMessage());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getException());
                $this->assertSame(MessageProcessorInterface::ACK, $context->getStatus());
                $this->assertTrue($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldAllowInterruptConsumingOnPostReceive()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $extension = $this->createExtension();
        $extension
            ->expects($this->once())
            ->method('onPostReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) {
                $context->setExecutionInterrupted(true);
            })
        ;
        $extension
            ->expects($this->atLeastOnce())
            ->method('onInterrupted')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertSame($expectedMessage, $context->getMessage());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getException());
                $this->assertSame(MessageProcessorInterface::ACK, $context->getStatus());
                $this->assertTrue($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallOnInterruptedIfExceptionThrow()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Process failed');
        $expectedException = new \Exception('Process failed');
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willThrowException($expectedException)
        ;

        $extension = $this->createExtension();
        $extension
            ->expects($this->atLeastOnce())
            ->method('onInterrupted')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use (
                $sessionStub,
                $messageConsumerStub,
                $messageProcessorMock,
                $expectedMessage,
                $expectedException
            ) {
                $this->assertSame($sessionStub, $context->getSession());
                $this->assertSame($messageConsumerStub, $context->getMessageConsumer());
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertSame($expectedMessage, $context->getMessage());
                $this->assertSame($expectedException, $context->getException());
                $this->assertInstanceOf(NullLogger::class, $context->getLogger());
                $this->assertNull($context->getStatus());
                $this->assertTrue($context->isExecutionInterrupted());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);
        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallExtensionPassedOnRuntime()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $runtimeExtension = $this->createExtension();
        $runtimeExtension
            ->expects($this->once())
            ->method('onStart')
            ->with($this->isInstanceOf(Context::class))
        ;
        $runtimeExtension
            ->expects($this->once())
            ->method('onBeforeReceive')
            ->with($this->isInstanceOf(Context::class))
        ;
        $runtimeExtension
            ->expects($this->once())
            ->method('onPreReceived')
            ->with($this->isInstanceOf(Context::class))
        ;
        $runtimeExtension
            ->expects($this->once())
            ->method('onPostReceived')
            ->with($this->isInstanceOf(Context::class))
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(1),
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume(new ChainExtension([$runtimeExtension]));
    }

    public function testShouldChangeLoggerOnStart()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        $expectedLogger = new NullLogger();

        $extension = $this->createExtension();
        $extension
            ->expects($this->atLeastOnce())
            ->method('onStart')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use ($expectedLogger) {
                $context->setLogger($expectedLogger);
            })
        ;
        $extension
            ->expects($this->atLeastOnce())
            ->method('onBeforeReceive')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use ($expectedLogger) {
                $this->assertSame($expectedLogger, $context->getLogger());
            })
        ;
        $extension
            ->expects($this->atLeastOnce())
            ->method('onPreReceived')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use ($expectedLogger) {
                $this->assertSame($expectedLogger, $context->getLogger());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $chainExtension = new ChainExtension([$extension, new BreakCycleExtension(1)]);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            $chainExtension,
            $this->createConsumerState(),
            0
        );
        $queueConsumer->bind('aQueueName', $messageProcessorMock);

        $queueConsumer->consume();
    }

    public function testShouldCallEachQueueOneByOne()
    {
        $expectedMessage = $this->createMessageMock();
        $messageConsumerStub = $this->createMessageConsumerStub($expectedMessage);

        $sessionStub = $this->createSessionStub($messageConsumerStub);

        $messageProcessorMock = $this->createMessageProcessorStub();
        $anotherMessageProcessorMock = $this->createMessageProcessorStub();

        $extension = $this->createExtension();
        $extension
            ->expects($this->at(1))
            ->method('onBeforeReceive')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use ($messageProcessorMock) {
                $this->assertSame($messageProcessorMock, $context->getMessageProcessor());
                $this->assertEquals('theQueueName', $context->getQueueName());
            })
        ;
        $extension
            ->expects($this->at(4))
            ->method('onBeforeReceive')
            ->with($this->isInstanceOf(Context::class))
            ->willReturnCallback(function (Context $context) use ($anotherMessageProcessorMock) {
                $this->assertSame($anotherMessageProcessorMock, $context->getMessageProcessor());
                $this->assertEquals('theAnotherQueueName', $context->getQueueName());
            })
        ;

        $connectionStub = $this->createConnectionStub($sessionStub);

        $queueConsumer = new QueueConsumer(
            $connectionStub,
            new BreakCycleExtension(2),
            $this->createConsumerState(),
            0
        );
        $queueConsumer
            ->bind('theQueueName', $messageProcessorMock)
            ->bind('theAnotherQueueName', $anotherMessageProcessorMock)
        ;

        $queueConsumer->consume(new ChainExtension([$extension]));
    }

    /**
     * @param MessageInterface|null $message
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|MessageConsumerInterface
     */
    protected function createMessageConsumerStub($message = null)
    {
        $messageConsumerMock = $this->createMock(MessageConsumerInterface::class);
        $messageConsumerMock
            ->expects($this->any())
            ->method('receive')
            ->willReturn($message)
        ;

        return $messageConsumerMock;
    }

    /**
     * @param SessionInterface|null $session
     *
     * @return ConnectionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createConnectionStub($session = null)
    {
        $connectionMock = $this->createMock(ConnectionInterface::class);
        $connectionMock
            ->expects($this->any())
            ->method('createSession')
            ->willReturn($session)
        ;

        return $connectionMock;
    }

    /**
     * @param MessageConsumerInterface|null $messageConsumer
     *
     * @return SessionInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createSessionStub($messageConsumer = null)
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock
            ->expects($this->any())
            ->method('createConsumer')
            ->willReturn($messageConsumer)
        ;
        $sessionMock
            ->expects($this->any())
            ->method('createQueue')
            ->willReturn($this->createMock(QueueInterface::class))
        ;
        $sessionMock
            ->expects($this->any())
            ->method('close')
        ;

        return $sessionMock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|MessageProcessorInterface
     */
    protected function createMessageProcessorMock()
    {
        return $this->createMock(MessageProcessorInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|MessageProcessorInterface
     */
    protected function createMessageProcessorStub()
    {
        $messageProcessorMock = $this->createMessageProcessorMock();
        $messageProcessorMock
            ->expects($this->any())
            ->method('process')
            ->willReturn(MessageProcessorInterface::ACK)
        ;

        return $messageProcessorMock;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|MessageInterface
     */
    protected function createMessageMock()
    {
        return $this->createMock(MessageInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ExtensionInterface
     */
    protected function createExtension()
    {
        return $this->createMock(ExtensionInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ConsumerState
     */
    protected function createConsumerState()
    {
        return $this->createMock(ConsumerState::class);
    }
}
