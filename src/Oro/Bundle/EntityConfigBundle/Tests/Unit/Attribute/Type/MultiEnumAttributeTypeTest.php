<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Attribute\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Type\MultiEnumAttributeType;
use Oro\Component\Testing\Unit\Entity\Stub\StubEnumValue;

class MultiEnumAttributeTypeTest extends AttributeTypeTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getAttributeType()
    {
        return new MultiEnumAttributeType();
    }

    /**
     * {@inheritdoc}
     */
    public function configurationMethodsDataProvider()
    {
        yield [
            'isSearchable' => true,
            'isFilterable' => true,
            'isSortable' => false
        ];
    }

    public function testGetSearchableValue()
    {
        $value1 = new StubEnumValue('id1', 'name1', 101);
        $value2 = new StubEnumValue('id2', 'name2', 102);

        $this->assertSame(
            'name1 name2',
            $this->getAttributeType()->getSearchableValue($this->attribute, [$value1, $value2], $this->localization)
        );
    }

    public function testGetSearchableValueTraversableException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array or Traversable, [string] given');

        $this->getAttributeType()->getSearchableValue($this->attribute, '', $this->localization);
    }

    public function testGetSearchableValueValueException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Value must be instance of "Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue", "integer" given'
        );

        $this->getAttributeType()->getSearchableValue($this->attribute, [42], $this->localization);
    }

    public function testGetFilterableValue()
    {
        $value1 = new StubEnumValue('id1', 'name1', 101);
        $value2 = new StubEnumValue('id2', 'name2', 102);

        $this->assertSame(
            [
                self::FIELD_NAME . '_id1' => 1,
                self::FIELD_NAME . '_id2' => 1
            ],
            $this->getAttributeType()->getFilterableValue($this->attribute, [$value1, $value2], $this->localization)
        );
    }

    public function testGetFilterableValueTraversableException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array or Traversable, [string] given');

        $this->getAttributeType()->getFilterableValue($this->attribute, '', $this->localization);
    }

    public function testGetFilterableValueValueException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Value must be instance of "Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue", "integer" given'
        );

        $this->getAttributeType()->getFilterableValue($this->attribute, [42], $this->localization);
    }

    public function testGetSortableValue()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->getAttributeType()->getSortableValue($this->attribute, true, $this->localization);
    }
}
