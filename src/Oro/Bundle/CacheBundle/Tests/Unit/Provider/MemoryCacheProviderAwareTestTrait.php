<?php

namespace Oro\Bundle\CacheBundle\Tests\Unit\Provider;

use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderInterface;

trait MemoryCacheProviderAwareTestTrait
{
    /** @var MemoryCacheProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $memoryCacheProvider;

    /**
     * @return MemoryCacheProviderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getMemoryCacheProvider(): MemoryCacheProviderInterface
    {
        if (!$this->memoryCacheProvider) {
            $this->memoryCacheProvider = $this->createMock(MemoryCacheProviderInterface::class);
        }

        return $this->memoryCacheProvider;
    }

    /**
     * @param mixed|null $cachedData
     */
    protected function mockMemoryCacheProvider($cachedData = null): void
    {
        $this->getMemoryCacheProvider()
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnCallback(
                static function ($cacheKeyArguments, $callable = null) use ($cachedData) {
                    if (!$cachedData && is_callable($callable)) {
                        return call_user_func_array($callable, (array) $cacheKeyArguments);
                    }

                    return $cachedData;
                }
            );
    }

    /**
     * @param object $object
     */
    protected function setMemoryCacheProvider(object $object): void
    {
        $object->setMemoryCacheProvider($this->getMemoryCacheProvider());
    }
}
