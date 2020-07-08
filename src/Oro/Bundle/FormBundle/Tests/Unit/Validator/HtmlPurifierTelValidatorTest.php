<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Validator;

use Oro\Bundle\FormBundle\Validator\HtmlPurifierTelValidator;

class HtmlPurifierTelValidatorTest extends \PHPUnit\Framework\TestCase
{
    /** @var HtmlPurifierTelValidator */
    protected $validator;

    /** @var \HTMLPurifier_URI */
    protected $uri;

    protected function setUp(): void
    {
        $this->validator = new HtmlPurifierTelValidator();
        $this->uri = new \HTMLPurifier_URI('scheme', 'userinfo', 'host', 'port', 'path', 'query', 'fragment');
    }

    /**
     * @param string $phone
     * @param bool $expectedValue
     *
     * @dataProvider phoneProvider
     */
    public function testPhones($phone, $expectedValue)
    {
        $this->uri->path = $phone;
        $this->assertEquals($expectedValue, $this->validator->doValidate($this->uri, null, null));
    }

    /**
     * @return array
     */
    public function phoneProvider()
    {
        return [
            [
                'phone' => '123456789',
                'expectedValue' => true
            ],
            [
                'phone' => '123-456-789',
                'expectedValue' => true
            ],
            [
                'phone' => '(123)-456-789',
                'expectedValue' => true
            ],
            [
                'phone' => '123.456.789',
                'expectedValue' => true
            ],
            [
                'phone' => 'not phone',
                'expectedValue' => false
            ],
            [
                'phone' => '654 75456',
                'expectedValue' => false
            ]
        ];
    }
}
