<?php

namespace Oro\Bundle\PlatformBundle\Tests\Unit\Manager;

use Oro\Bundle\PlatformBundle\Manager\OptionalListenerManager;
use Oro\Bundle\PlatformBundle\Tests\Unit\Fixtures\TestListener;

class OptionalListenerManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var OptionalListenerManager
     */
    protected $manager;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $container;

    /**
     * @var array
     */
    protected $testListeners;

    protected function setUp(): void
    {
        $this->testListeners = [
            'test.listener1',
            'test.listener2',
            'test.listener3',
        ];
        $this->container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new OptionalListenerManager($this->testListeners, $this->container);
    }

    public function testGetListeners()
    {
        $this->assertEquals($this->testListeners, $this->manager->getListeners());
    }

    public function testDisableListener()
    {
        $testListener = new TestListener();
        $testListener->enabled = false;
        $listenerId = 'test.listener2';
        $this->container->expects($this->once())
            ->method('get')
            ->with($listenerId)
            ->will($this->returnValue($testListener));
        $this->manager->disableListener($listenerId);
        $this->assertFalse($testListener->enabled);
    }

    public function testDisableNonExistsListener()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Listener "test.bad_listener" does not exist or not optional');

        $this->manager->disableListener('test.bad_listener');
    }

    public function testDisableOneListener()
    {
        $testListener = new TestListener();
        $testListener->enabled = false;
        $listenerId = 'test.listener3';
        $this->container->expects($this->once())
            ->method('get')
            ->with($listenerId)
            ->will($this->returnValue($testListener));
        $this->manager->disableListeners([$listenerId]);
        $this->assertFalse($testListener->enabled);
    }
}
