<?php

namespace Oro\Bundle\CommentBundle\Tests\Unit\Entity;

abstract class AbstractEntityTestCase extends \PHPUnit\Framework\TestCase
{
    const TEST_ID = 123;

    protected $entity;

    protected function setUp(): void
    {
        $name         = $this->getEntityFQCN();
        $this->entity = new $name();
    }

    protected function tearDown(): void
    {
        unset($this->entity);
    }

    /**
     * @dataProvider  getSetDataProvider
     *
     * @param string $property
     * @param mixed  $value
     * @param mixed  $expected
     */
    public function testSetGet($property, $value = null, $expected = null)
    {
        if ($value !== null) {
            call_user_func_array(array($this->entity, 'set' . ucfirst($property)), array($value));
        }

        $this->assertEquals($expected, call_user_func_array(array($this->entity, 'get' . ucfirst($property)), array()));
    }

    /**
     * @return array
     */
    abstract public function getSetDataProvider();

    /**
     * @return string
     */
    abstract public function getEntityFQCN();
}
