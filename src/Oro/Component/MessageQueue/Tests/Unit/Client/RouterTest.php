<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Client;

use Oro\Component\MessageQueue\Client\Config;
use Oro\Component\MessageQueue\Client\DriverInterface;
use Oro\Component\MessageQueue\Client\Meta\DestinationMetaRegistry;
use Oro\Component\MessageQueue\Client\Router;
use Oro\Component\MessageQueue\Router\Recipient;
use Oro\Component\MessageQueue\Router\RecipientListRouterInterface;
use Oro\Component\MessageQueue\Transport\Message;
use Oro\Component\MessageQueue\Transport\Queue;
use Oro\Component\Testing\ClassExtensionTrait;

class RouterTest extends \PHPUnit\Framework\TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementRecipientListRouterInterface()
    {
        $this->assertClassImplements(RecipientListRouterInterface::class, Router::class);
    }

    public function testCouldBeConstructedWithSessionAsFirstArgument()
    {
        new Router($this->createDriverStub(), $this->createDestinationMetaRegistry());
    }

    public function testCouldBeConstructedWithSessionAndRoutes()
    {
        $routes = [
            'aTopicName' => [['aProcessorName', 'aQueueName']],
            'anotherTopicName' => [['aProcessorName', 'aQueueName']]
        ];

        $router = new class($this->createDriverStub(), $this->createDestinationMetaRegistry(), $routes) extends Router {
            public function xgetRoutes(): array
            {
                return $this->routes;
            }
        };

        static::assertEquals($routes, $router->xgetRoutes());
    }

    public function testThrowIfTopicNameEmptyOnOnAddRoute()
    {
        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The topic name must not be empty');
        $router->addRoute('', 'aProcessorName', 'aQueueName');
    }

    public function testThrowIfProcessorNameEmptyOnOnAddRoute()
    {
        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The processor name must not be empty');
        $router->addRoute('aTopicName', '', 'aQueueName');
    }

    public function testShouldAllowAddRouteWithQueueSetExplicitly()
    {
        $router = new class($this->createDriverStub(), $this->createDestinationMetaRegistry()) extends Router {
            public function xgetRoutes(): array
            {
                return $this->routes;
            }
        };

        $router->addRoute('aTopicName', 'aProcessorName', 'aQueueName');

        static::assertEquals(['aTopicName' => [['aProcessorName', 'aQueueName']]], $router->xgetRoutes());
    }

    public function testShouldAllowAddTwoRoutesForSameTopic()
    {
        $router = new class($this->createDriverStub(), $this->createDestinationMetaRegistry()) extends Router {
            public function xgetRoutes(): array
            {
                return $this->routes;
            }
        };

        $router->addRoute('aTopicName', 'aFooProcessorName', 'aFooQueueName');
        $router->addRoute('aTopicName', 'aBarProcessorName', 'aBarQueueName');

        static::assertEquals(
            ['aTopicName' => [['aFooProcessorName', 'aFooQueueName'], ['aBarProcessorName', 'aBarQueueName']]],
            $router->xgetRoutes()
        );
    }

    public function testShouldAllowAddRouteWithDefaultQueue()
    {
        $router = new class($this->createDriverStub(), $this->createDestinationMetaRegistry()) extends Router {
            public function xgetRoutes(): array
            {
                return $this->routes;
            }
        };

        $router->addRoute('aTopicName', 'aProcessorName', 'default');

        static::assertEquals(['aTopicName' => [['aProcessorName', 'default']]], $router->xgetRoutes());
    }

    public function testShouldThrowExceptionIfTopicNameParameterIsNotSet()
    {
        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Got message without required parameter: "oro.message_queue.client.topic_name"');
        $result = $router->route(new Message());

        iterator_to_array($result);
    }

    public function testThrowIfQueueNameEmptyOnOnAddRoute()
    {
        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The queue name must not be empty');
        $router->addRoute('aTopicName', 'aProcessorName', '');
    }

    public function testShouldRouteOriginalMessageToRecipientAndDefaultQueue()
    {
        $message = new Message();
        $message->setBody('theBody');
        $message->setHeaders(['aHeader' => 'aHeaderVal']);
        $message->setProperties(['aProp' => 'aPropVal', Config::PARAMETER_TOPIC_NAME => 'theTopicName']);

        $driver = $this->createDriverStub();

        $destinationsMeta = [
            'default' => []
        ];

        $router = new Router($driver, $this->createDestinationMetaRegistry($destinationsMeta));
        $router->addRoute('theTopicName', 'aFooProcessor', 'default');

        $result = $router->route($message);
        $result = iterator_to_array($result);

        $this->assertCount(1, $result);
        /** @var Recipient $recipient */
        $recipient = $result[0];
        $this->assertInstanceOf(Recipient::class, $recipient);

        $this->assertInstanceOf(Queue::class, $recipient->getQueue());
        $this->assertEquals('aprefix.adefaultqueuename', $recipient->getQueue()->getQueueName());

        $newMessage = $recipient->getMessage();
        $this->assertInstanceOf(Message::class, $newMessage);
        $this->assertEquals('aprefix.adefaultqueuename', $newMessage->getProperty(Config::PARAMETER_QUEUE_NAME));
    }

    public function testShouldRouteOriginalMessageToRecipientToCustomQueue()
    {
        $message = new Message();
        $message->setBody('theBody');
        $message->setHeaders(['aHeader' => 'aHeaderVal']);
        $message->setProperties(['aProp' => 'aPropVal', Config::PARAMETER_TOPIC_NAME => 'theTopicName']);

        $destinationsMeta = [
            'aFooQueue' => []
        ];

        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry($destinationsMeta));
        $router->addRoute('theTopicName', 'aFooProcessor', 'aFooQueue');

        $result = $router->route($message);
        $result = iterator_to_array($result);

        $this->assertCount(1, $result);
        /** @var Recipient $recipient */
        $recipient = $result[0];
        $this->assertInstanceOf(Recipient::class, $recipient);

        $this->assertInstanceOf(Queue::class, $recipient->getQueue());
        $this->assertEquals('aprefix.afooqueue', $recipient->getQueue()->getQueueName());

        $newMessage = $recipient->getMessage();
        $this->assertInstanceOf(Message::class, $newMessage);
        $this->assertEquals('theBody', $newMessage->getBody());
        $this->assertEquals(
            [
                'aProp' => 'aPropVal',
                Config::PARAMETER_TOPIC_NAME => 'theTopicName',
                Config::PARAMETER_PROCESSOR_NAME => 'aFooProcessor',
                Config::PARAMETER_QUEUE_NAME => 'aprefix.afooqueue',
            ],
            $newMessage->getProperties()
        );
        $this->assertEquals(['aHeader' => 'aHeaderVal'], $newMessage->getHeaders());
    }

    public function testShouldRouteOriginalMessageToTwoRecipients()
    {
        $message = new Message();
        $message->setProperties([Config::PARAMETER_TOPIC_NAME => 'theTopicName']);

        $destinationsMeta = [
            'aFooQueue' => [],
            'aBarQueue' => []
        ];

        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry($destinationsMeta));
        $router->addRoute('theTopicName', 'aFooProcessor', 'aFooQueue');
        $router->addRoute('theTopicName', 'aBarProcessor', 'aBarQueue');


        $result = $router->route($message);
        $result = iterator_to_array($result);

        $this->assertCount(2, $result);
        $this->assertContainsOnly(Recipient::class, $result);
    }

    public function testShouldRouteOriginalMessageToCustomTransportQueue()
    {
        $message = new Message();
        $message->setProperties([Config::PARAMETER_TOPIC_NAME => 'theTopicName']);

        $destinationsMeta = [
            'aFooQueue' => ['transportName' => 'acustomqueue'],
        ];

        $router = new Router($this->createDriverStub(), $this->createDestinationMetaRegistry($destinationsMeta));
        $router->addRoute('theTopicName', 'aFooProcessor', 'aFooQueue');

        $result = $router->route($message);
        $result = iterator_to_array($result);

        $this->assertCount(1, $result);
        /** @var Recipient $recipient */
        $recipient = $result[0];
        $this->assertInstanceOf(Recipient::class, $recipient);

        $this->assertInstanceOf(Queue::class, $recipient->getQueue());
        $this->assertEquals('acustomqueue', $recipient->getQueue()->getQueueName());
    }

    /**
     * @param array $destinationsMeta
     *
     * @return DestinationMetaRegistry
     */
    protected function createDestinationMetaRegistry(array $destinationsMeta = [])
    {
        $config = new Config('aPrefix', 'aRouterMessageProcessorName', 'aRouterQueueName', 'aDefaultQueueName');

        return new DestinationMetaRegistry($config, $destinationsMeta, 'default');
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|DriverInterface
     */
    protected function createDriverStub()
    {
        $driverMock = $this->createMock(DriverInterface::class);
        $driverMock
            ->expects($this->any())
            ->method('createQueue')
            ->willReturnCallback(function ($queueName) {
                return new Queue($queueName);
            })
        ;

        return $driverMock;
    }
}
