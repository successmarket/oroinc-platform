<?php


namespace Oro\Bundle\ThemeBundle\Tests\Unit\Model;

use Oro\Bundle\ThemeBundle\Model\ThemeRegistry;

class ThemeRegistryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ThemeRegistry
     */
    protected $themeRegistry;

    protected $themeSettings = [
        'foo' => [
            'label' => 'Foo Theme',
            'icon' => 'favicon.ico',
            'logo' => 'logo.png',
            'screenshot' => 'screenshot.png'
        ],
        'bar' => [
        ]
    ];

    protected function setUp(): void
    {
        $this->themeRegistry = new ThemeRegistry($this->themeSettings);
    }

    public function testGetTheme()
    {
        $fooTheme = $this->themeRegistry->getTheme('foo');
        $this->assertInstanceOf('Oro\Bundle\ThemeBundle\Model\Theme', $fooTheme);
        $this->assertEquals('Foo Theme', $fooTheme->getLabel());
        $this->assertEquals('favicon.ico', $fooTheme->getIcon());
        $this->assertEquals('logo.png', $fooTheme->getLogo());
        $this->assertEquals('screenshot.png', $fooTheme->getScreenshot());
        $this->assertSame($fooTheme, $this->themeRegistry->getTheme('foo'));

        $barTheme = $this->themeRegistry->getTheme('bar');
        $this->assertInstanceOf('Oro\Bundle\ThemeBundle\Model\Theme', $barTheme);
        $this->assertNull($barTheme->getLabel());
        $this->assertNull($barTheme->getIcon());
        $this->assertNull($barTheme->getLogo());
        $this->assertNull($barTheme->getScreenshot());
        $this->assertSame($barTheme, $this->themeRegistry->getTheme('bar'));

        $this->assertEquals(
            array('foo' => $fooTheme, 'bar' => $barTheme),
            $this->themeRegistry->getAllThemes()
        );
    }

    public function testGetThemeNotFoundException()
    {
        $this->expectException(\Oro\Bundle\ThemeBundle\Exception\ThemeNotFoundException::class);
        $this->expectExceptionMessage('Theme "baz" not found.');

        $this->themeRegistry->getTheme('baz');
    }

    public function testGetActiveTheme()
    {
        $this->assertNull($this->themeRegistry->getActiveTheme());
        $this->themeRegistry->setActiveTheme('foo');
        $activeTheme = $this->themeRegistry->getActiveTheme();
        $this->assertInstanceOf('Oro\Bundle\ThemeBundle\Model\Theme', $activeTheme);
        $this->assertEquals('foo', $activeTheme->getName());
    }
}
