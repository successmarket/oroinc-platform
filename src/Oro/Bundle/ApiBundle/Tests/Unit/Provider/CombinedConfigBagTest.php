<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Config\EntityConfigMerger;
use Oro\Bundle\ApiBundle\Provider\CombinedConfigBag;
use Oro\Bundle\ApiBundle\Provider\ConfigBag;
use Oro\Bundle\ApiBundle\Provider\ConfigBagInterface;

class CombinedConfigBagTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|ConfigBagInterface */
    private $configBag1;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ConfigBagInterface */
    private $configBag2;

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityConfigMerger */
    private $entityConfigMerger;

    /** @var CombinedConfigBag */
    private $combinedConfigBag;

    protected function setUp(): void
    {
        $this->configBag1 = $this->createMock(ConfigBag::class);
        $this->configBag2 = $this->createMock(ConfigBagInterface::class);
        $this->entityConfigMerger = $this->createMock(EntityConfigMerger::class);

        $this->combinedConfigBag = new CombinedConfigBag(
            [$this->configBag1, $this->configBag2],
            $this->entityConfigMerger
        );
    }

    public function testGetClassNames()
    {
        $version = '1.2';

        $this->configBag1->expects(self::once())
            ->method('getClassNames')
            ->with($version)
            ->willReturn(['Test\Class2', 'Test\Class3']);
        $this->configBag2->expects(self::once())
            ->method('getClassNames')
            ->with($version)
            ->willReturn(['Test\Class1', 'Test\Class2']);

        self::assertEquals(
            ['Test\Class2', 'Test\Class3', 'Test\Class1'],
            $this->combinedConfigBag->getClassNames($version)
        );
    }

    public function testNoConfig()
    {
        $className = 'Test\Class1';
        $version = '1.2';

        $this->configBag1->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(null);
        $this->configBag2->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(null);
        $this->entityConfigMerger->expects(self::never())
            ->method('merge');

        self::assertNull(
            $this->combinedConfigBag->getConfig($className, $version)
        );
        // test that data is cached in memory
        self::assertNull(
            $this->combinedConfigBag->getConfig($className, $version)
        );
    }

    public function testOnlyFirstBagHasConfig()
    {
        $className = 'Test\Class1';
        $version = '1.2';

        $this->configBag1->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(['fields' => ['field1' => []]]);
        $this->configBag2->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(null);
        $this->entityConfigMerger->expects(self::never())
            ->method('merge');

        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
        // test that data is cached in memory
        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
    }

    public function testOnlySecondBagHasConfig()
    {
        $className = 'Test\Class1';
        $version = '1.2';

        $this->configBag1->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(null);
        $this->configBag2->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(['fields' => ['field1' => []]]);
        $this->entityConfigMerger->expects(self::never())
            ->method('merge');

        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
        // test that data is cached in memory
        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
    }

    public function testAllBagsHaveConfigs()
    {
        $className = 'Test\Class1';
        $version = '1.2';

        $this->configBag1->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(['fields' => ['field1' => []]]);
        $this->configBag2->expects(self::once())
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(['fields' => ['field2' => []]]);
        $this->entityConfigMerger->expects(self::once())
            ->method('merge')
            ->with(['fields' => ['field1' => []]], ['fields' => ['field2' => []]])
            ->willReturn(['fields' => ['field1' => [], 'field2' => []]]);

        self::assertEquals(
            ['fields' => ['field1' => [], 'field2' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
        // test that data is cached in memory
        self::assertEquals(
            ['fields' => ['field1' => [], 'field2' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
    }

    public function testReset()
    {
        $className = 'Test\Class1';
        $version = '1.2';

        $this->configBag1->expects(self::exactly(2))
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(null);
        $this->configBag2->expects(self::exactly(2))
            ->method('getConfig')
            ->with($className, $version)
            ->willReturn(['fields' => ['field1' => []]]);
        $this->entityConfigMerger->expects(self::never())
            ->method('merge');
        $this->configBag1->expects(self::once())
            ->method('reset');

        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
        // test that data is cached in memory
        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );

        $this->combinedConfigBag->reset();
        self::assertEquals(
            ['fields' => ['field1' => []]],
            $this->combinedConfigBag->getConfig($className, $version)
        );
    }
}
