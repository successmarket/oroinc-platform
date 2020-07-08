<?php

namespace Oro\Bundle\SoapBundle\Tests\Unit\Handler;

use Oro\Bundle\SoapBundle\Handler\DelegateIncludeHandler;
use Oro\Bundle\SoapBundle\Handler\IncludeHandlerInterface;
use Symfony\Component\DependencyInjection\Container;

class DelegateIncludeHandlerTest extends \PHPUnit\Framework\TestCase
{
    use ContextAwareTest;

    /** @var Container */
    protected $container;

    /** @var DelegateIncludeHandler */
    protected $handler;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->handler   = new DelegateIncludeHandler($this->container);
    }

    protected function tearDown(): void
    {
        unset($this->handler, $this->container);
    }

    public function testSupports()
    {
        $this->assertTrue($this->handler->supports($this->createContext()));
    }

    public function testUnknownIncludes()
    {
        $testUnsupported = implode(IncludeHandlerInterface::DELIMITER, ['include1', 'include2']);

        $context = $this->createContext();
        $context->getRequest()->headers->set(IncludeHandlerInterface::HEADER_INCLUDE, $testUnsupported);

        $this->handler->handle($context);

        $response = $context->getResponse();
        $this->assertTrue($response->headers->has(IncludeHandlerInterface::HEADER_UNKNOWN));
        $this->assertFalse($response->headers->has(IncludeHandlerInterface::HEADER_UNSUPPORTED));

        $this->assertSame($testUnsupported, $response->headers->get(IncludeHandlerInterface::HEADER_UNKNOWN));
    }

    public function testUnsupportedIncludes()
    {
        $includeName = 'lastModified';
        $context     = $this->createContext();
        $context->getRequest()->headers->set(IncludeHandlerInterface::HEADER_INCLUDE, $includeName);

        $serviceId = 'acme.demo.last-modified.handler';
        $handler   = $this->createMock('Oro\Bundle\SoapBundle\Handler\IncludeHandlerInterface');
        $this->container->set($serviceId, $handler);
        $this->handler->registerHandler($includeName, $serviceId);

        $handler->expects($this->once())->method('supports')
            ->with($this->isInstanceOf('Oro\Bundle\SoapBundle\Handler\Context'))
            ->willReturn(false);

        $this->handler->handle($context);

        $response = $context->getResponse();
        $this->assertFalse($response->headers->has(IncludeHandlerInterface::HEADER_UNKNOWN));
        $this->assertTrue($response->headers->has(IncludeHandlerInterface::HEADER_UNSUPPORTED));

        $this->assertSame($includeName, $response->headers->get(IncludeHandlerInterface::HEADER_UNSUPPORTED));
    }

    public function testSupportedIncludes()
    {
        $includeName = 'lastModified';
        $context     = $this->createContext();
        $context->getRequest()->headers->set(IncludeHandlerInterface::HEADER_INCLUDE, $includeName);

        $serviceId = 'acme.demo.last-modified.handler';
        $handler   = $this->createMock('Oro\Bundle\SoapBundle\Handler\IncludeHandlerInterface');
        $this->container->set($serviceId, $handler);
        $this->handler->registerHandler($includeName, $serviceId);

        $handler->expects($this->once())->method('supports')
            ->with($this->isInstanceOf('Oro\Bundle\SoapBundle\Handler\Context'))
            ->willReturn(true);
        $handler->expects($this->once())->method('handle')
            ->with($this->isInstanceOf('Oro\Bundle\SoapBundle\Handler\Context'));

        $this->handler->handle($context);

        $response = $context->getResponse();
        $this->assertFalse($response->headers->has(IncludeHandlerInterface::HEADER_UNKNOWN));
        $this->assertFalse($response->headers->has(IncludeHandlerInterface::HEADER_UNSUPPORTED));
    }
}
