<?php

namespace Oro\Bundle\CacheBundle\Tests\Unit\Generator;

use Oro\Bundle\CacheBundle\Generator\ObjectCacheKeyGenerator;
use Oro\Bundle\CacheBundle\Generator\UniversalCacheKeyGenerator;

class UniversalCacheKeyGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /** @var ObjectCacheKeyGenerator|\PHPUnit\Framework\MockObject\MockObject */
    private $objectCacheKeyGenerator;

    /** @var UniversalCacheKeyGenerator */
    private $generator;

    protected function setUp(): void
    {
        $this->objectCacheKeyGenerator = $this->createMock(ObjectCacheKeyGenerator::class);

        $this->generator = new UniversalCacheKeyGenerator($this->objectCacheKeyGenerator);
    }

    /**
     * @dataProvider generateDataProvider
     *
     * @param array|string $arguments
     * @param string $expectedCacheKey
     */
    public function testGenerate($arguments, string $expectedCacheKey): void
    {
        $this->objectCacheKeyGenerator
            ->expects($this->any())
            ->method('generate')
            ->with($this->isInstanceOf(\stdClass::class), $scope = 'sample_scope')
            ->willReturn('sample_key');

        $this->assertEquals(sha1($expectedCacheKey), $this->generator->generate($arguments));
    }

    /**
     * @return array
     */
    public function generateDataProvider(): array
    {
        return [
            'number' => [
                'arguments' => 10,
                'expectedCacheKey' => '10',
            ],
            'string' => [
                'arguments' => 'sample_argument',
                'expectedCacheKey' => 'sample_argument',
            ],
            'boolean' => [
                'arguments' => false,
                'expectedCacheKey' => '0',
            ],
            'object' => [
                'arguments' => ['sample_scope' => new \stdClass()],
                'expectedCacheKey' => 'sample_key',
            ],
            'object with scope in array' => [
                'arguments' => [
                    ['sample_scope' => [new \stdClass()]],
                ],
                'expectedCacheKey' => 'sample_key',
            ],
            'mix of different types' => [
                'arguments' => [
                    'sample_scope' => new \stdClass(),
                    ['sample_scope' => new \stdClass()],
                    true,
                    'sample_string',
                    3,
                ],
                'expectedCacheKey' => 'sample_key|sample_key|1|sample_string|3',
            ],
        ];
    }
}
