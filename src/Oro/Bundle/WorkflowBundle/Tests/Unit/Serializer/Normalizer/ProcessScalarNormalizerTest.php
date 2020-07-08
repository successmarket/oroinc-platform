<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Serializer\Normalizer;

use Oro\Bundle\WorkflowBundle\Serializer\Normalizer\ProcessScalarNormalizer;

class ProcessScalarNormalizerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ProcessScalarNormalizer
     */
    protected $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ProcessScalarNormalizer();
    }

    public function testNormalize()
    {
        $value = 'scalar';
        $this->assertEquals($value, $this->normalizer->normalize($value));
    }

    public function testDenormalize()
    {
        $value = 'scalar';
        $this->assertEquals($value, $this->normalizer->denormalize($value, null));
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupportsNormalization($data, $expected)
    {
        $this->assertEquals($expected, $this->normalizer->supportsNormalization($data));
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupportsDenormalization($data, $expected)
    {
        $this->assertEquals($expected, $this->normalizer->supportsDenormalization($data, null));
    }

    public function supportsDataProvider()
    {
        return array(
            'null'   => array(null, true),
            'scalar' => array('scalar', true),
            'array' => array(array(), false),
            'object' => array(new \DateTime(), false),
        );
    }
}
