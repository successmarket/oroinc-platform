<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Form\DataTransformer;

use Oro\Bundle\LocaleBundle\Form\DataTransformer\FallbackValueTransformer;
use Oro\Bundle\LocaleBundle\Model\FallbackType;

class FallbackValueTransformerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FallbackValueTransformer
     */
    protected $transformer;

    protected function setUp(): void
    {
        $this->transformer = new FallbackValueTransformer();
    }

    /**
     * @param mixed $input
     * @param mixed $expected
     * @dataProvider transformDataProvider
     */
    public function testTransform($input, $expected): void
    {
        $this->assertEquals($expected, $this->transformer->transform($input));
    }

    /**
     * @return array
     */
    public function transformDataProvider(): array
    {
        return [
            'null' => [
                'input'    => null,
                'expected' => ['value' => null, 'use_fallback' => false, 'fallback' => null],
            ],
            'scalar' => [
                'input'    => 'string',
                'expected' => ['value' => 'string', 'use_fallback' => false, 'fallback' => null],
            ],
            'fallback' => [
                'input'    => new FallbackType(FallbackType::SYSTEM),
                'expected' => ['value' => null, 'use_fallback' => true, 'fallback' => FallbackType::SYSTEM],
            ],
        ];
    }

    /**
     * @param mixed $input
     * @param mixed $expected
     * @dataProvider reverseTransformDataProvider
     */
    public function testReverseTransform($input, $expected): void
    {
        $this->assertSame($expected, $this->transformer->reverseTransform($input));
    }

    /**
     * @return array
     */
    public function reverseTransformDataProvider(): array
    {
        return [
            'null' => [
                'input'    => null,
                'expected' => null,
            ],
            'empty array' => [
                'input'    => [],
                'expected' => null,
            ],
            'empty values' => [
                'input'    => ['value' => null, 'fallback' => null],
                'expected' => '',
            ],
            'scalar' => [
                'input'    => ['value' => 'string', 'fallback' => null],
                'expected' => 'string',
            ],
        ];
    }

    /**
     * @param mixed $input
     * @param mixed $expected
     * @dataProvider reverseTransformWhenFallbackDataProvider
     */
    public function testReverseTransformWhenFallback($input, $expected): void
    {
        $this->assertEquals($expected, $this->transformer->reverseTransform($input));
    }

    /**
     * @return array
     */
    public function reverseTransformWhenFallbackDataProvider(): array
    {
        return [
            'fallback' => [
                'input' => ['value' => null, 'fallback' => FallbackType::SYSTEM, 'use_fallback' => true],
                'expected' => new FallbackType(FallbackType::SYSTEM),
            ],
            'when not use_fallback than value' => [
                'input' => ['value' => 'string', 'fallback' => FallbackType::SYSTEM, 'use_fallback' => false],
                'expected' => 'string',
            ],
            'when use_fallback than fallback' => [
                'input' => ['value' => 'string', 'fallback' => FallbackType::SYSTEM, 'use_fallback' => true],
                'expected' => new FallbackType(FallbackType::SYSTEM),
            ],
            'use_fallback required fallback' => [
                'input' => ['value' => 'string', 'use_fallback' => true],
                'expected' => 'string',
            ],
        ];
    }
}
