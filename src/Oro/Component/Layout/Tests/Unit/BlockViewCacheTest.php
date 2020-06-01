<?php

namespace Oro\Component\Layout\Tests\Unit;

use Doctrine\Common\Cache\CacheProvider;
use Oro\Bundle\CacheBundle\Provider\ArrayCache;
use Oro\Component\Layout\BlockView;
use Oro\Component\Layout\BlockViewCache;
use Oro\Component\Layout\LayoutContext;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class BlockViewCacheTest extends LayoutTestCase
{
    /** @var BlockView */
    protected $blockView;

    /**
     * @var BlockViewCache
     */
    protected $blockViewCache;

    /**
     * @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $cacheProvider;

    /**
     * @var Serializer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $serializer;

    const CONTEXT_HASH_VALUE = 'context_hash_value';

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->blockView = new BlockView();

        $this->cacheProvider = $this->getMockBuilder(CacheProvider::class)
            ->setMethods(['fetch', 'contains', 'save', 'deleteAll'])->getMockForAbstractClass();

        $normalizer = new ObjectNormalizer();
        $this->serializer = new Serializer([$normalizer], [new JsonEncoder()]);
        $normalizer->setSerializer($this->serializer);

        $this->blockViewCache = new BlockViewCache($this->cacheProvider, $this->serializer);
    }

    public function testSave()
    {
        $context = $this->getMockBuilder(LayoutContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context->expects(static::once())
            ->method('getHash')
            ->willReturn($this::CONTEXT_HASH_VALUE);

        $this->cacheProvider->expects(static::once())
            ->method('save')
            ->with($this::CONTEXT_HASH_VALUE, '[]');

        $this->blockViewCache->save($context, $this->blockView);
    }

    public function testFetchNonCached()
    {
        $context = $this->getMockBuilder(LayoutContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context->expects(static::once())
            ->method('getHash')
            ->willReturn($this::CONTEXT_HASH_VALUE);

        $this->cacheProvider->expects(static::once())
            ->method('fetch')
            ->with($this::CONTEXT_HASH_VALUE)
            ->willReturn(false);

        $this->assertNull($this->blockViewCache->fetch($context));
    }

    public function testFetchCached()
    {
        $context = $this->getMockBuilder(LayoutContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context->expects(static::once())
            ->method('getHash')
            ->willReturn($this::CONTEXT_HASH_VALUE);

        $this->cacheProvider->expects(static::once())
            ->method('fetch')
            ->with($this::CONTEXT_HASH_VALUE)
            ->willReturn('[]');

        $context->expects(static::once())
            ->method('getHash')
            ->willReturn($this::CONTEXT_HASH_VALUE);

        $fetchedBlockView = $this->blockViewCache->fetch($context);

        $this->assertEquals($this->blockView, $fetchedBlockView);
    }

    public function testReset()
    {
        $this->cacheProvider->expects(static::once())
            ->method('deleteAll');

        $this->blockViewCache->reset();
    }

    public function testCacheWhenContextWithFilledData()
    {
        $normalizer = $this->createMock(ObjectNormalizer::class);
        $normalizer->expects($this->any())
            ->method('supportsNormalization')
            ->willReturn(true);
        $normalizer->expects($this->any())
            ->method('supportsDenormalization')
            ->willReturn(true);
        $normalizer->expects($this->any())
            ->method('normalize')
            ->willReturnCallback(function ($data, $format, $context) {
                return $data->vars;
            });
        $normalizer->expects($this->any())
            ->method('denormalize')
            ->willReturnCallback(function ($data) {
                if (!$data) {
                    return null;
                }

                $object = new BlockView();
                $object->vars = $data;

                return $object;
            });
        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);

        $cache = new BlockViewCache(new ArrayCache(), $serializer);
        $context = new LayoutContext(['some data']);
        $firstBlockView = new BlockView();
        $firstBlockView->vars = ['attr' => 'first block view data'];
        $secondContext = new LayoutContext(['some data']);
        $secondContext->data()->set('custom_data_key', 'custom_data_value');
        $secondBlockView = new BlockView();
        $secondBlockView->vars = ['attr' => 'second block view data'];

        $context->getResolver()->setDefined([0]);
        $context->resolve();
        $secondContext->getResolver()->setDefined([0]);
        $secondContext->resolve();

        $cache->save($context, $firstBlockView);
        $cache->save($secondContext, $secondBlockView);

        self::assertEquals($firstBlockView, $cache->fetch($context));
        self::assertEquals($secondBlockView, $cache->fetch($secondContext));

        $secondContextWithAdditionalData = new LayoutContext(['some data']);
        $secondContextWithAdditionalData->data()->set('custom_data_key', 'custom_data_value');
        $secondContextWithAdditionalData->data()->set('additional_data_key', 'additional_data_value');
        $secondContextWithAdditionalData->getResolver()->setDefined([0]);
        $secondContextWithAdditionalData->resolve();
        self::assertNull($cache->fetch($secondContextWithAdditionalData));
    }
}
