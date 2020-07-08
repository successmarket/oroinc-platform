<?php

namespace Oro\Component\Layout\Tests\Unit\Extension;

use Oro\Component\Layout\Exception\InvalidArgumentException;
use Oro\Component\Layout\Extension\PreloadedExtension;

class PreloadedExtensionTest extends \PHPUnit\Framework\TestCase
{
    public function testTypes()
    {
        $name = 'test';
        $type = $this->createMock('Oro\Component\Layout\BlockTypeInterface');

        $extension = new PreloadedExtension(
            [
                $name => $type
            ]
        );

        $this->assertTrue($extension->hasType($name));
        $this->assertFalse($extension->hasType('unknown'));

        $this->assertSame($type, $extension->getType($name));
    }

    public function testGetUnknownType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The type "unknown" can not be loaded by this extension.');

        $extension = new PreloadedExtension([]);

        $extension->getType('unknown');
    }

    public function testBlockTypeExtensions()
    {
        $name          = 'test';
        $typeExtension = $this->createMock('Oro\Component\Layout\BlockTypeExtensionInterface');

        $extension = new PreloadedExtension(
            [],
            [
                $name => [$typeExtension]
            ]
        );

        $this->assertTrue($extension->hasTypeExtensions($name));
        $this->assertFalse($extension->hasTypeExtensions('unknown'));

        $this->assertCount(1, $extension->getTypeExtensions($name));
        $this->assertSame($typeExtension, $extension->getTypeExtensions($name)[0]);

        $this->assertSame([], $extension->getTypeExtensions('unknown'));
    }

    public function testGetLayoutUpdates()
    {
        $id           = 'test';
        $layoutUpdate = $this->createMock('Oro\Component\Layout\LayoutUpdateInterface');

        $extension = new PreloadedExtension(
            [],
            [],
            [
                $id => [$layoutUpdate]
            ]
        );

        $layoutItem = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItem->expects($this->once())->method('getId')->willReturn($id);
        $layoutItemUnknown = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItemUnknown->expects($this->once())->method('getId')->willReturn('unknown');
        $layoutItemAlias = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItemAlias->expects($this->never())->method('getId');
        $layoutItemAlias->expects($this->once())->method('getAlias')->willReturn($id);

        $layoutUpdates = $extension->getLayoutUpdates($layoutItem);
        $this->assertCount(1, $layoutUpdates);
        $this->assertSame($layoutUpdate, $layoutUpdates[0]);

        $this->assertSame([], $extension->getLayoutUpdates($layoutItemUnknown));

        $layoutUpdates = $extension->getLayoutUpdates($layoutItemAlias);
        $this->assertCount(1, $layoutUpdates);
        $this->assertSame($layoutUpdate, $layoutUpdates[0]);
    }

    public function testHasLayoutUpdates()
    {
        $id           = 'test';
        $layoutUpdate = $this->createMock('Oro\Component\Layout\LayoutUpdateInterface');

        $extension = new PreloadedExtension(
            [],
            [],
            [
                $id => [$layoutUpdate]
            ]
        );

        $layoutItem = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItem->expects($this->once())->method('getId')->willReturn($id);
        $layoutItemUnknown = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItemUnknown->expects($this->once())->method('getId')->willReturn('unknown');
        $layoutItemAlias = $this->createMock('Oro\Component\Layout\LayoutItemInterface');
        $layoutItemAlias->expects($this->never())->method('getId');
        $layoutItemAlias->expects($this->once())->method('getAlias')->willReturn($id);

        $this->assertTrue($extension->hasLayoutUpdates($layoutItem));
        $this->assertFalse($extension->hasLayoutUpdates($layoutItemUnknown));
        $this->assertTrue($extension->hasLayoutUpdates($layoutItemAlias));
    }

    public function testContextConfigurators()
    {
        $configurator = $this->createMock('Oro\Component\Layout\ContextConfiguratorInterface');

        $extension = new PreloadedExtension(
            [],
            [],
            [],
            [$configurator]
        );

        $this->assertTrue($extension->hasContextConfigurators());

        $result = $extension->getContextConfigurators();
        $this->assertCount(1, $result);
        $this->assertSame($configurator, $result[0]);
    }

    public function testContextConfiguratorsWheNoAnyRegistered()
    {
        $extension = new PreloadedExtension([]);

        $this->assertFalse($extension->hasContextConfigurators());
        $this->assertSame([], $extension->getContextConfigurators());
    }

    public function testDataProviders()
    {
        $name         = 'test';
        $dataProvider = $this->createMock(\stdClass::class);

        $extension = new PreloadedExtension(
            [],
            [],
            [],
            [],
            [
                $name => $dataProvider
            ]
        );

        $this->assertTrue($extension->hasDataProvider($name));
        $this->assertFalse($extension->hasDataProvider('unknown'));

        $this->assertSame($dataProvider, $extension->getDataProvider($name));
    }

    public function testGetUnknownDataProvider()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The data provider "unknown" can not be loaded by this extension.');

        $extension = new PreloadedExtension([], [], [], [], []);

        $extension->getDataProvider('unknown');
    }

    public function testConstructWithInvalidKeysForBlockTypes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Keys of $types array must be strings.');

        new PreloadedExtension(
            [
                'test' => $this->createMock('Oro\Component\Layout\BlockTypeInterface'),
                $this->createMock('Oro\Component\Layout\BlockTypeInterface')
            ]
        );
    }

    public function testConstructWithInvalidBlockTypes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item of $types array must be BlockTypeInterface.');

        new PreloadedExtension(
            [
                'test1' => $this->createMock('Oro\Component\Layout\BlockTypeInterface'),
                'test2' => new \stdClass()
            ]
        );
    }

    public function testConstructWithInvalidKeysForBlockTypeExtensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Keys of $typeExtensions array must be strings.');

        new PreloadedExtension(
            [],
            [
                'test' => [$this->createMock('Oro\Component\Layout\BlockTypeExtensionInterface')],
                [$this->createMock('Oro\Component\Layout\BlockTypeExtensionInterface')]
            ]
        );
    }

    public function testConstructWithInvalidBlockTypeExtensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item of $typeExtensions[] array must be BlockTypeExtensionInterface.');

        new PreloadedExtension(
            [],
            [
                'test1' => [$this->createMock('Oro\Component\Layout\BlockTypeExtensionInterface')],
                'test2' => [new \stdClass()]
            ]
        );
    }

    public function testConstructWithSingleBlockTypeExtensions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Each item of $typeExtensions array must be array of BlockTypeExtensionInterface.'
        );

        new PreloadedExtension(
            [],
            [
                'test' => $this->createMock('Oro\Component\Layout\BlockTypeExtensionInterface')
            ]
        );
    }

    public function testConstructWithInvalidKeysForLayoutUpdates()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Keys of $layoutUpdates array must be strings.');

        new PreloadedExtension(
            [],
            [],
            [
                'test' => [$this->createMock('Oro\Component\Layout\LayoutUpdateInterface')],
                [$this->createMock('Oro\Component\Layout\LayoutUpdateInterface')]
            ]
        );
    }

    public function testConstructWithInvalidLayoutUpdates()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item of $layoutUpdates[] array must be LayoutUpdateInterface.');

        new PreloadedExtension(
            [],
            [],
            [
                'test1' => [$this->createMock('Oro\Component\Layout\LayoutUpdateInterface')],
                'test2' => [new \stdClass()]
            ]
        );
    }

    public function testConstructWithSingleLayoutUpdates()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each item of $layoutUpdates array must be array of LayoutUpdateInterface.');

        new PreloadedExtension(
            [],
            [],
            [
                'test' => $this->createMock('Oro\Component\Layout\LayoutUpdateInterface')
            ]
        );
    }

    public function testConstructWithInvalidContextConfiguratorType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Each item of $contextConfigurators array must be ContextConfiguratorInterface.'
        );

        new PreloadedExtension(
            [],
            [],
            [],
            [
                $this->createMock('Oro\Component\Layout\ContextConfiguratorInterface'),
                new \stdClass()
            ]
        );
    }

    public function testConstructWithInvalidKeysForDataProviders()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Keys of $dataProviders array must be strings.');

        new PreloadedExtension(
            [],
            [],
            [],
            [],
            [
                'test' => $this->createMock(\stdClass::class),
                $this->createMock(\stdClass::class)
            ]
        );
    }
}
