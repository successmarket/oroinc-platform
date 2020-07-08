<?php

namespace Oro\Component\MessageQueue\Tests\Unit\Job\Extension;

use Oro\Component\MessageQueue\Job\Extension\ChainExtension;
use Oro\Component\MessageQueue\Job\Extension\ExtensionInterface;
use Oro\Component\MessageQueue\Job\Job;

class ChainExtensionTest extends \PHPUnit\Framework\TestCase
{
    /** @var ChainExtension */
    protected $chainExtension;

    /** @var ExtensionInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $subExtension;

    protected function setUp(): void
    {
        $this->subExtension = $this->createMock(ExtensionInterface::class);
        $this->chainExtension = new ChainExtension([$this->subExtension]);
    }

    public function testOnPreRunUnique()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPreRunUnique')
            ->with($job);

        $this->chainExtension->onPreRunUnique($job);
    }

    public function testOnPostRunUnique()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPostRunUnique')
            ->with($job, true);

        $this->chainExtension->onPostRunUnique($job, true);
    }

    public function testOnPreRunDelayed()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPreRunDelayed')
            ->with($job);

        $this->chainExtension->onPreRunDelayed($job);
    }

    public function testOnPostRunDelayed()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPostRunDelayed')
            ->with($job, true);

        $this->chainExtension->onPostRunDelayed($job, true);
    }

    public function testOnPreCreateDelayed()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPreCreateDelayed')
            ->with($job);

        $this->chainExtension->onPreCreateDelayed($job);
    }

    public function testOnPostCreateDelayed()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onPostCreateDelayed')
            ->with($job, true);

        $this->chainExtension->onPostCreateDelayed($job, true);
    }

    public function testOnCancel()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onCancel')
            ->with($job);

        $this->chainExtension->onCancel($job);
    }


    public function testOnError()
    {
        $job = new Job();

        $this->subExtension->expects($this->once())
            ->method('onError')
            ->with($job);

        $this->chainExtension->onError($job);
    }
}
