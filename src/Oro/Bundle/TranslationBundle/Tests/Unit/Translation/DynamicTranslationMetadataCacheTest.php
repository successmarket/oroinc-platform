<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Translation;

use Oro\Bundle\TranslationBundle\Translation\DynamicTranslationMetadataCache;

class DynamicTranslationMetadataCacheTest extends \PHPUnit\Framework\TestCase
{
    /** @var DynamicTranslationMetadataCache */
    protected $metadataCache;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    protected $cacheImpl;

    protected function setUp(): void
    {
        $this->cacheImpl = $this->getMockBuilder('Doctrine\Common\Cache\CacheProvider')
            ->setMethods(['fetch', 'save'])
            ->getMockForAbstractClass();
        $this->metadataCache = new DynamicTranslationMetadataCache($this->cacheImpl);
    }

    public function testTimestamp()
    {
        $this->cacheImpl->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue(1));

        $result = $this->metadataCache->getTimestamp('en_USSR');
        $this->assertEquals(1, $result);

        $this->cacheImpl
            ->expects($this->once())
            ->method('save');

        $this->metadataCache->updateTimestamp('en');
    }
}
