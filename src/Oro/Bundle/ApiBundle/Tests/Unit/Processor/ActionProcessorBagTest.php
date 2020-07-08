<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor;

use Oro\Bundle\ApiBundle\Processor\ActionProcessorBag;
use Oro\Component\ChainProcessor\ActionProcessor;

class ActionProcessorBagTest extends \PHPUnit\Framework\TestCase
{
    public function testGetProcessor()
    {
        $processor = $this->createMock(ActionProcessor::class);
        $processor->expects(self::once())
            ->method('getAction')
            ->willReturn('test');

        $actionProcessorBag = new ActionProcessorBag();
        $actionProcessorBag->addProcessor($processor);

        self::assertSame($processor, $actionProcessorBag->getProcessor('test'));
    }

    public function testGetUnknownProcessor()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A processor for "unknown" action was not found.');

        $processor = $this->createMock(ActionProcessor::class);
        $processor->expects(self::once())
            ->method('getAction')
            ->willReturn('test');

        $actionProcessorBag = new ActionProcessorBag();
        $actionProcessorBag->addProcessor($processor);

        $actionProcessorBag->getProcessor('unknown');
    }
}
