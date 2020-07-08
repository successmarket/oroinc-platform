<?php

namespace Oro\Bundle\WebsiteBundle\Tests\Unit\Resolver;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EmailBundle\Provider\UrlProvider;
use Oro\Component\Testing\Unit\EntityTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlProviderTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /**
     * @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configManager;

    /**
     * @var UrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $urlGenerator;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    protected function setUp(): void
    {
        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlProvider = new UrlProvider($this->configManager, $this->urlGenerator);
    }

    public function testGetAbsoluteUrlWithSubfolderPath()
    {
        $route = 'test';
        $routeParams = ['id' => 1 ];
        $url = 'http://global.website.url/some/subfolder/';

        $this->configManager->expects($this->once())
            ->method('get')
            ->with(UrlProvider::APPLICATION_URL)
            ->willReturn($url);

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with($route, $routeParams)
            ->willReturn('/some/subfolder/test/1');

        $this->assertSame(
            'http://global.website.url/some/subfolder/test/1',
            $this->urlProvider->getAbsoluteUrl($route, $routeParams)
        );
    }
}
