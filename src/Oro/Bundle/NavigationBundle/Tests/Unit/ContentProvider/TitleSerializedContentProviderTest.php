<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\ContentProvider;

use Oro\Bundle\NavigationBundle\ContentProvider\TitleSerializedContentProvider;

class TitleSerializedContentProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $titleService;

    /**
     * @var TitleSerializedContentProvider
     */
    protected $provider;

    protected function setUp(): void
    {
        $this->titleService = $this->getMockBuilder('Oro\Bundle\NavigationBundle\Provider\TitleServiceInterface')
            ->getMock();

        $this->provider = new TitleSerializedContentProvider($this->titleService);
    }

    public function testGetContent()
    {
        $this->titleService->expects($this->once())
            ->method('getSerialized')
            ->will($this->returnValue('title_content'));
        $this->assertEquals('title_content', $this->provider->getContent());
    }
}
