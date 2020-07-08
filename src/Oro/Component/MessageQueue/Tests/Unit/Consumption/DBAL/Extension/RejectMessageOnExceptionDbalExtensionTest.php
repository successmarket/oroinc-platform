<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Consumption\DBAL\Extension;

use Oro\Component\MessageQueue\Consumption\Context;
use Oro\Component\MessageQueue\Consumption\Dbal\Extension\RejectMessageOnExceptionDbalExtension;
use Oro\Component\MessageQueue\Transport\Message;
use Oro\Component\MessageQueue\Transport\MessageConsumerInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerInterface;

class RejectMessageOnExceptionDbalExtensionTest extends \PHPUnit\Framework\TestCase
{
    public function testCouldBeCreatedWithRequiredArguments()
    {
        new RejectMessageOnExceptionDbalExtension();
    }

    public function testShouldDoNothingIfExceptionIsMissing()
    {
        $consumer = $this->createMessageConsumerMock();
        $consumer
            ->expects($this->never())
            ->method('reject')
        ;

        $context = new Context($this->createSessionMock());
        $context->setMessageConsumer($consumer);

        $extension = new RejectMessageOnExceptionDbalExtension();
        $extension->onInterrupted($context);
    }

    public function testShouldDoNothingIfMessageIsMissing()
    {
        $consumer = $this->createMessageConsumerMock();
        $consumer
            ->expects($this->never())
            ->method('reject')
        ;

        $context = new Context($this->createSessionMock());
        $context->setException(new \Exception());
        $context->setMessageConsumer($consumer);

        $extension = new RejectMessageOnExceptionDbalExtension();
        $extension->onInterrupted($context);
    }

    public function testShouldRejectMessage()
    {
        $message = new Message();
        $message->setMessageId(123);

        $logger = $this->createLoggerMock();
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Execution was interrupted and message was rejected. {id}',
                ['id' => '123']
            )
        ;

        $consumer = $this->createMessageConsumerMock();
        $consumer
            ->expects($this->once())
            ->method('reject')
            ->with($this->identicalTo($message), $this->isTrue())
        ;

        $context = new Context($this->createSessionMock());
        $context->setLogger($logger);
        $context->setException(new \Exception());
        $context->setMessage($message);
        $context->setMessageConsumer($consumer);

        $extension = new RejectMessageOnExceptionDbalExtension();
        $extension->onInterrupted($context);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function createMessageConsumerMock()
    {
        return $this->createMock(MessageConsumerInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|SessionInterface
     */
    private function createSessionMock()
    {
        return $this->createMock(SessionInterface::class);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private function createLoggerMock()
    {
        return $this->createMock(LoggerInterface::class);
    }
}
