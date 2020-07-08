<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Event;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Event\EventDispatcher;
use Oro\Bundle\DataGridBundle\Provider\SystemAwareResolver;
use Oro\Bundle\DataGridBundle\Tests\Unit\Stub\GridConfigEvent;
use Oro\Bundle\DataGridBundle\Tests\Unit\Stub\GridEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventDispatcherTest extends \PHPUnit\Framework\TestCase
{
    const TEST_EVENT_NAME = 'test.event';

    /** @var  EventDispatcherInterface */
    protected $dispatcher;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $realDispatcherMock;

    protected function setUp(): void
    {
        $this->realDispatcherMock = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->dispatcher         = new EventDispatcher($this->realDispatcherMock);
    }

    protected function tearDown(): void
    {
        unset($this->realDispatcherMock, $this->dispatcher);
    }

    /**
     * @dataProvider eventDataProvider
     *
     * @param array $config
     * @param array $expectedEvents
     */
    public function testDispatchGridEvent(array $config, array $expectedEvents)
    {
        $config   = DatagridConfiguration::create($config);
        $gridMock = $this->createMock('Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface');
        $gridMock->expects($this->any())->method('getConfig')->will($this->returnValue($config));

        foreach ($expectedEvents as $k => $event) {
            $this->realDispatcherMock->expects($this->at($k))->method('dispatch')
                ->with(new GridEvent($gridMock), $event);
        }

        $event = new GridEvent($gridMock);
        $this->dispatcher->dispatch(self::TEST_EVENT_NAME, $event);
    }

    /**
     * @return array
     */
    public function eventDataProvider()
    {
        return [
            'should raise at least 2 events'          => [
                ['name' => 'testGrid'],
                [self::TEST_EVENT_NAME, self::TEST_EVENT_NAME . '.' . 'testGrid']
            ],
            'should raise 3 events start with parent' => [
                ['name' => 'testGrid', SystemAwareResolver::KEY_EXTENDED_FROM => ['parent1']],
                [
                    self::TEST_EVENT_NAME,
                    self::TEST_EVENT_NAME . '.' . 'parent1',
                    self::TEST_EVENT_NAME . '.' . 'testGrid'
                ]
            ]
        ];
    }

    /**
     * @dataProvider eventDataProvider
     *
     * @param array $config
     * @param array $expectedEvents
     */
    public function testDispatchGridConfigEvent(array $config, array $expectedEvents)
    {
        $config   = DatagridConfiguration::create($config);

        $event = new GridConfigEvent($config);

        foreach ($expectedEvents as $k => $eventName) {
            $this->realDispatcherMock->expects($this->at($k))->method('dispatch')
                ->with($event, $eventName);
        }

        $this->dispatcher->dispatch(self::TEST_EVENT_NAME, $event);
    }
    public function testDispatchException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unexpected event type. Expected instance of GridEventInterface or GridConfigurationEventInterface'
        );
        $event = $this->getMockBuilder('Symfony\Component\EventDispatcher\Event')
            ->disableOriginalConstructor();
        $this->dispatcher->dispatch($event);
    }
}
