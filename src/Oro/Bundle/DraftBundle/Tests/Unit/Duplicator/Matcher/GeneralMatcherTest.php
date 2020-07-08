<?php

namespace Oro\Bundle\DraftBundle\Tests\Unit\Duplicator\Matcher;

use Oro\Bundle\DraftBundle\Duplicator\Matcher\GeneralMatcher;
use Oro\Bundle\DraftBundle\Tests\Unit\Stub\DraftableEntityStub;
use Oro\Component\Testing\Unit\EntityTrait;

class GeneralMatcherTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /** @var GeneralMatcher */
    private $matcher;

    protected function setUp(): void
    {
        $this->matcher = new GeneralMatcher();
    }

    /**
     * @param mixed $value
     *
     * @dataProvider typeDataProvider
     */
    public function testMatches($value): void
    {
        $matches = $this->matcher->matches($value, '');
        $this->assertTrue($matches);
    }

    /**
     * @return array
     */
    public function typeDataProvider(): array
    {
        return [
            'string' => ['string'],
            'boolean' => ['integer'],
            'integer' => [1],
            'array' => [[]],
            'object' => [new DraftableEntityStub()],
            'null' => [null],
        ];
    }
}
