<?php
namespace Oro\Component\MessageQueue\Tests\Unit\Consumption\DBAL;

use Oro\Component\MessageQueue\Consumption\Dbal\DbalCliProcessManager;

class DbalCliProcessManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testShouldReturnListOfProcessesPids()
    {
        $processManager = new DbalCliProcessManager();

        $pids = $processManager->getListOfProcessesPids('');

        $this->assertGreaterThan(0, count($pids));
        $this->assertIsInt($pids[0]);
    }
}
