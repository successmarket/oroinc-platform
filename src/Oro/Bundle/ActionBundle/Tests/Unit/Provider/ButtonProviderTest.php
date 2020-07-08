<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ActionBundle\Button\ButtonContext;
use Oro\Bundle\ActionBundle\Button\ButtonInterface;
use Oro\Bundle\ActionBundle\Button\ButtonsCollection;
use Oro\Bundle\ActionBundle\Button\ButtonSearchContext;
use Oro\Bundle\ActionBundle\Extension\ButtonProviderExtensionInterface;
use Oro\Bundle\ActionBundle\Provider\ButtonProvider;
use Oro\Bundle\ActionBundle\Provider\Event\OnButtonsMatched;
use Oro\Bundle\ActionBundle\Tests\Unit\Stub\StubButton;
use Oro\Bundle\TestFrameworkBundle\Test\Stub\CallableStub;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ButtonProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $eventDispatcher;

    /** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $logger;

    /** @var ButtonSearchContext|\PHPUnit\Framework\MockObject\MockObject */
    private $searchContext;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->searchContext = $this->createMock(ButtonSearchContext::class);
    }

    public function testMatch()
    {
        $button1 = $this->getButton();
        $button2 = $this->getButton();
        $button3 = $this->getButton();

        $extension1 = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension1->expects($this->once())->method('find')->willReturn([$button1]);
        $extension2 = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension2->expects($this->once())->method('find')->willReturn([$button2, $button3]);

        $buttonProvider = $this->getButtonProvider([$extension1, $extension2]);
        $collection = $buttonProvider->match($this->searchContext);
        $this->assertInstanceOf(ButtonsCollection::class, $collection);

        //checking correct mapping button => extension at collection
        $callable = $this->createMock(CallableStub::class);
        $callable->expects($this->at(0))
            ->method('__invoke')
            ->with(
                $this->identicalTo($button1),
                $this->identicalTo($extension1)
            )->willReturn($button1);

        $callable->expects($this->at(1))
            ->method('__invoke')
            ->with(
                $this->identicalTo($button2),
                $this->identicalTo($extension2)
            )->willReturn($button2);

        $callable->expects($this->at(2))
            ->method('__invoke')
            ->with(
                $this->identicalTo($button3),
                $this->identicalTo($extension2)
            )->willReturn($button3);

        $collection->map($callable);
    }

    public function testMatchEvent()
    {
        $extension1 = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension1->expects($this->once())->method('find')->willReturn([]);
        $extension2 = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension2->expects($this->once())->method('find')->willReturn([]);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(OnButtonsMatched::NAME, new OnButtonsMatched(new ButtonsCollection()));

        $buttonProvider = $this->getButtonProvider([$extension1, $extension2]);
        $buttonProvider->match($this->searchContext);
    }

    public function testFindAvailableWithErrors()
    {
        $extension = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension->expects($this->once())->method('find')->willReturn([$this->getButton()]);
        $extension->expects($this->once())
            ->method('isAvailable')
            ->willReturnCallback(
                function ($button, $searchContext, ArrayCollection $errors) {
                    $errors->add(['message' => 'error message', 'parameters' => ['param1']]);
                }
            );

        $this->logger->expects($this->once())->method('error')->with('error message', ['param1']);

        $buttonProvider = $this->getButtonProvider([$extension]);
        $buttonProvider->findAvailable($this->searchContext);
    }

    /**
     * @dataProvider findAllDataProvider
     *
     * @param array $input
     * @param array $output
     */
    public function testFindAll(array $input, array $output)
    {
        $extension = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension->expects($this->once())
            ->method('find')
            ->with($this->searchContext)
            ->willReturn($input);

        $buttonProvider = $this->getButtonProvider([$extension]);
        $this->assertEquals($output, $buttonProvider->findAll($this->searchContext));
    }

    /**
     * @return array
     */
    public function findAllDataProvider()
    {
        $button1 = $this->getButton(1);
        $button2 = $this->getButton(2);
        $button3 = $this->getButton(3);

        return [
            'no input' => [
                'input' => [],
                'output' => []
            ],
            'one button' => [
                'input' => [$button2],
                'output' => [$button2]
            ],
            'just ordered' => [
                'input' => [$button2, $button1, $button3],
                'output' => [$button1, $button2, $button3]
            ],
            'with same will be overridden' => [
                'input' => [$button3, $button3, $button2],
                'output' => [$button2, $button3]
            ]
        ];
    }

    public function testFindAllWithErrors()
    {
        $extension = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension->expects($this->once())->method('find')->willReturn([$this->getButton()]);
        $extension->expects($this->once())
            ->method('isAvailable')
            ->willReturnCallback(
                function ($button, $searchContext, ArrayCollection $errors) {
                    $errors->add(['message' => 'error message', 'parameters' => ['param1']]);
                }
            );

        $this->logger->expects($this->once())->method('error')->with('error message', ['param1']);

        $buttonProvider = $this->getButtonProvider([$extension]);
        $buttonProvider->findAll($this->searchContext);
    }

    public function testHasButtons()
    {
        $extension = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension->expects($this->once())
            ->method('find')
            ->with($this->searchContext)
            ->willReturn([$this->getButton()]);

        $buttonProvider = $this->getButtonProvider([$extension]);
        $this->assertTrue($buttonProvider->hasButtons($this->searchContext));
    }

    public function testHasButtonsWithoutButtons()
    {
        $extension = $this->createMock(ButtonProviderExtensionInterface::class);
        $extension->expects($this->once())
            ->method('find')
            ->with($this->searchContext)
            ->willReturn([]);

        $buttonProvider = $this->getButtonProvider([$extension]);
        $this->assertFalse($buttonProvider->hasButtons($this->searchContext));
    }

    /**
     * @param ButtonProviderExtensionInterface[] $extensions
     *
     * @return ButtonProvider
     */
    private function getButtonProvider(array $extensions)
    {
        return new ButtonProvider(
            $extensions,
            $this->eventDispatcher,
            $this->logger
        );
    }

    /**
     * @param int $order
     * @return ButtonInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function getButton($order = 1)
    {
        return new StubButton(
            [
                'order' => $order,
                'templateData' => ['additionalData' => []],
                'buttonContext' => new ButtonContext()
            ]
        );
    }
}
