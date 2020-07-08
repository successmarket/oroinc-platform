<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Doctrine\Common\Cache\Cache;
use Oro\Bundle\EntityBundle\Model\EntityAlias;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityBundle\Provider\EntityAliasLoader;
use Oro\Bundle\EntityBundle\Provider\EntityAliasStorage;
use Oro\Component\Config\Cache\ConfigCacheStateInterface;
use Psr\Log\LoggerInterface;

class EntityAliasResolverTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityAliasLoader */
    private $loader;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Cache */
    private $cache;

    /** @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface */
    private $logger;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ConfigCacheStateInterface */
    private $configCacheState;

    /** @var EntityAliasResolver */
    private $entityAliasResolver;

    protected function setUp(): void
    {
        $this->loader = $this->createMock(EntityAliasLoader::class);
        $this->cache = $this->createMock(Cache::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configCacheState = $this->createMock(ConfigCacheStateInterface::class);

        $this->entityAliasResolver = new EntityAliasResolver(
            $this->loader,
            $this->cache,
            $this->logger
        );
    }

    private function setLoadExpectations()
    {
        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn(false);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });
    }

    public function testHasAliasForUnknownEntity()
    {
        $this->setLoadExpectations();

        self::assertFalse(
            $this->entityAliasResolver->hasAlias('Test\UnknownEntity')
        );
    }

    public function testGetAliasForUnknownEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('An alias for "Test\UnknownEntity" entity not found.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getAlias('Test\UnknownEntity');
    }

    public function testGetPluralAliasForUnknownEntity()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('A plural alias for "Test\UnknownEntity" entity not found.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getPluralAlias('Test\UnknownEntity');
    }

    public function testGetClassByAliasForUnknownAlias()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('The alias "unknown" is not associated with any entity class.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getClassByAlias('unknown');
    }

    public function testGetClassByPluralAliasForUnknownAlias()
    {
        $this->expectException(\Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('The plural alias "unknown" is not associated with any entity class.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getClassByPluralAlias('unknown');
    }

    public function testHasAlias()
    {
        $this->setLoadExpectations();

        self::assertTrue(
            $this->entityAliasResolver->hasAlias('Test\Entity1')
        );
    }

    public function testGetAlias()
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'entity1_alias',
            $this->entityAliasResolver->getAlias('Test\Entity1')
        );
    }

    public function testGetPluralAlias()
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'entity1_plural_alias',
            $this->entityAliasResolver->getPluralAlias('Test\Entity1')
        );
    }

    public function testGetClassByAlias()
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'Test\Entity1',
            $this->entityAliasResolver->getClassByAlias('entity1_alias')
        );
    }

    public function testGetClassByPluralAlias()
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'Test\Entity1',
            $this->entityAliasResolver->getClassByPluralAlias('entity1_plural_alias')
        );
    }

    public function testGetAll()
    {
        $this->setLoadExpectations();

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testWarmUpCache()
    {
        $this->cache->expects(self::once())
            ->method('delete')
            ->with('entity_aliases');

        $this->setLoadExpectations();

        $this->entityAliasResolver->warmUpCache();
    }

    public function testClearCache()
    {
        $this->cache->expects(self::once())
            ->method('delete')
            ->with('entity_aliases');

        $this->entityAliasResolver->clearCache();
    }

    public function testLoad()
    {
        $loadedStorage = new EntityAliasStorage();
        $loadedStorage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn(false);
        $this->cache->expects(self::once())
            ->method('save')
            ->with('entity_aliases', [null, $loadedStorage]);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCache()
    {
        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn([null, $storage]);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheTimestampIsNull()
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn([null, $storage]);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with(self::isNull())
            ->willReturn(true);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheIsFresh()
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $timestamp = 123;

        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn([$timestamp, $storage]);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with($timestamp)
            ->willReturn(true);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheIsDirty()
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $previousTimestamp = 123;
        $newTimestamp = 124;

        $cachedStorage = new EntityAliasStorage();
        $cachedStorage->addEntityAlias('Test\Entity2', new EntityAlias('entity2_alias', 'entity2_plural_alias'));

        $loadedStorage = new EntityAliasStorage();
        $loadedStorage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cache->expects(self::once())
            ->method('fetch')
            ->with('entity_aliases')
            ->willReturn([$previousTimestamp, $cachedStorage]);
        $this->cache->expects(self::once())
            ->method('save')
            ->with('entity_aliases', [$newTimestamp, $loadedStorage]);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with($previousTimestamp)
            ->willReturn(false);
        $this->configCacheState->expects(self::once())
            ->method('getCacheTimestamp')
            ->willReturn($newTimestamp);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }
}
