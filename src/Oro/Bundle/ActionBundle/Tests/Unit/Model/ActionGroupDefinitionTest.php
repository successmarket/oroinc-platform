<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Model;

use Oro\Bundle\ActionBundle\Model\ActionGroupDefinition;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;

class ActionGroupDefinitionTest extends \PHPUnit\Framework\TestCase
{
    use EntityTestCaseTrait;

    /** @var ActionGroupDefinition */
    protected $actionGroupDefinition;

    protected function setUp(): void
    {
        $this->actionGroupDefinition = new ActionGroupDefinition();
    }

    protected function tearDown(): void
    {
        unset($this->actionGroupDefinition);
    }

    public function testGettersAndSetters()
    {
        static::assertPropertyAccessors(
            $this->actionGroupDefinition,
            [
                ['name', 'test'],
                ['actions', ['config1', 'config2'], []],
                ['conditions', ['config1', 'config2'], []],
                ['parameters', ['config1', 'config2'], []],
            ]
        );
    }
}
