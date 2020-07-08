<?php
namespace Oro\Bundle\MessageQueueBundle\Tests\Functional\EventListener;

use Oro\Bundle\EntityExtendBundle\Event\UpdateSchemaEvent;
use Oro\Bundle\MessageQueueBundle\EventListener\UpdateSchemaListener;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UpdateSchemaListenerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient();
    }

    protected function tearDown(): void
    {
        $filePath = $this->getContainer()->getParameter('oro_message_queue.consumption.interrupt_filepath');

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        parent::tearDown();
    }

    public function testMustBeListeningForUpdateSchemaEvent()
    {
        $dispatcher = $this->getEventDispatcher();

        $isListenerExist = false;
        foreach ($dispatcher->getListeners(UpdateSchemaEvent::NAME) as $listener) {
            if (! $listener[0] instanceof UpdateSchemaListener) {
                $isListenerExist = true;
                break;
            }
        }

        $this->assertTrue($isListenerExist);
    }
    
    public function testMustCreateFileIfNotExistOnUpdateSchemaEvent()
    {
        $filePath = $this->getContainer()->getParameter('oro_message_queue.consumption.interrupt_filepath');

        $this->assertFileDoesNotExist($filePath);

        $this->removeListenersForEventExceptTested();

        $this->dispatchUpdateSchemaEvent();

        $this->assertFileExists($filePath);
    }

    public function testMustUpdateFileMetadataOnUpdateSchemaEvent()
    {
        $filePath = $this->getContainer()->getParameter('oro_message_queue.consumption.interrupt_filepath');
        $directory = dirname($filePath);

        @mkdir($directory, 0777, true);
        touch($filePath, time() - 1);

        $this->assertFileExists($filePath);

        $timestamp = filemtime($filePath);

        $this->removeListenersForEventExceptTested();

        $this->dispatchUpdateSchemaEvent();

        clearstatcache(true, $filePath);

        $this->assertNotEquals($timestamp, filemtime($filePath));
    }

    /**
     * Remove all listeners except UpdateSchemaListener for UpdateSchemaEvent
     */
    protected function removeListenersForEventExceptTested()
    {
        $dispatcher = $this->getEventDispatcher();

        foreach ($dispatcher->getListeners(UpdateSchemaEvent::NAME) as $listener) {
            if (! $listener[0] instanceof UpdateSchemaListener) {
                $dispatcher->removeListener(UpdateSchemaEvent::NAME, $listener);
            }
        }
    }

    /**
     * Dispatch UpdateSchemaEvent
     */
    protected function dispatchUpdateSchemaEvent()
    {
        $dispatcher = $this->getEventDispatcher();

        $event = $this->createMock(UpdateSchemaEvent::class);
        $dispatcher->dispatch(UpdateSchemaEvent::NAME, $event);
    }

    /**
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher()
    {
        return $this->getContainer()->get('event_dispatcher');
    }
}
