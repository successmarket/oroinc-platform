<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Attribute\Type;

use Oro\Bundle\EntityConfigBundle\Attribute\Type\TextAttributeType;
use Oro\Component\Testing\Unit\Entity\Stub\StubEnumValue;

class TextAttributeTypeTest extends AttributeTypeTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function getAttributeType()
    {
        return new TextAttributeType();
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
        $string = 'test';

        $this->assertSame(
            $string,
            $this->getAttributeType()
                ->getSearchableValue($this->attribute, new StubEnumValue('id', $string), $this->localization)
        );
    }

    public function testGetFilterableValue()
    {
        $string = 'test';

        $this->assertSame(
            $string,
            $this->getAttributeType()
                ->getFilterableValue($this->attribute, new StubEnumValue('id', $string), $this->localization)
        );
    }

    public function testGetSortableValue()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not supported');

        $this->getAttributeType()->getSortableValue($this->attribute, true, $this->localization);
    }
}
