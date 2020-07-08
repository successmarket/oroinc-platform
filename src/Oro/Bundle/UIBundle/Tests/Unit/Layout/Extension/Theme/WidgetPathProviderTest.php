<?php

namespace Oro\Bundle\UIBundle\Tests\Unit\Layout\Extension\Theme;

use Oro\Bundle\UIBundle\Layout\Extension\Theme\WidgetPathProvider;
use Oro\Component\Layout\LayoutContext;

class WidgetPathProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var WidgetPathProvider */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new WidgetPathProvider();
    }

    /**
     * @dataProvider pathsDataProvider
     *
     * @param string[]    $existingPaths
     * @param string|null $widget
     * @param string[]    $expectedPaths
     */
    public function testGetPaths($existingPaths, $widget, $expectedPaths)
    {
        $context = new LayoutContext();
        $context->set('widget_container', $widget);
        $this->provider->setContext($context);
        $this->assertSame($expectedPaths, $this->provider->getPaths($existingPaths));
    }

    /**
     * @return array
     */
    public function pathsDataProvider()
    {
        return [
            [
                'existingPaths' => [],
                'widget'        => null,
                'expectedPaths' => []
            ],
            [
                'existingPaths' => [],
                'widget'        => 'dialog',
                'expectedPaths' => []
            ],
            [
                'existingPaths' => [
                    'base',
                    'base/action',
                    'base/route'
                ],
                'widget'        => null,
                'expectedPaths' => [
                    'base',
                    'base/page',
                    'base/action',
                    'base/action/page',
                    'base/route',
                    'base/route/page'
                ]
            ],
            [
                'existingPaths' => [
                    'base',
                    'base/action',
                    'base/route'
                ],
                'widget'        => 'dialog',
                'expectedPaths' => [
                    'base',
                    'base/dialog',
                    'base/action',
                    'base/action/dialog',
                    'base/route',
                    'base/route/dialog'
                ]
            ]
        ];
    }
}
