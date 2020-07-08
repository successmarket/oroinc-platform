<?php

namespace Oro\Component\Layout\Tests\Unit;

use Oro\Component\Layout\StringOptionValueBuilder;

class StringOptionValueBuilderTest extends \PHPUnit\Framework\TestCase
{
    public function testConstructWithInvalidDelimiter()
    {
        $this->expectException(\Oro\Component\Layout\Exception\UnexpectedTypeException::class);
        $builder = new StringOptionValueBuilder(null);
    }

    public function testBuildWithDefaultOptions()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('val1');
        $builder->add('val2');
        $builder->add('val3 val4');
        $builder->remove('val2');
        $builder->replace('val3', 'replaced_val3');
        $this->assertEquals('val1 replaced_val3 val4', $builder->get());
    }

    public function testBuildWithoutTokenize()
    {
        $builder = new StringOptionValueBuilder(' ', false);
        $builder->add('val1');
        $builder->add('val2');
        $builder->add('val3 val4');
        $builder->remove('val2');
        $builder->replace('val3', 'replaced_val3');
        $this->assertEquals('val1 val3 val4', $builder->get());
    }

    public function testBuildWithCustomDelimiter()
    {
        $builder = new StringOptionValueBuilder(',');
        $builder->add('val1');
        $builder->add('val2');
        $builder->add('val3,val4');
        $builder->remove('val2');
        $builder->replace('val3', 'replaced_val3');
        $this->assertEquals('val1,replaced_val3,val4', $builder->get());
    }

    public function testBuildWithoutDelimiter()
    {
        $builder = new StringOptionValueBuilder('');
        $builder->add('val1');
        $builder->add('val2');
        $builder->add('val3');
        $builder->remove('val2');
        $builder->replace('val3', 'replaced_val3');
        $this->assertEquals('val1replaced_val3', $builder->get());
    }

    public function testAddThrowsExceptionIfInvalidValue()
    {
        $this->expectException(\Oro\Component\Layout\Exception\UnexpectedTypeException::class);
        $builder = new StringOptionValueBuilder();
        $builder->add(123);
    }

    public function testRemoveThrowsExceptionIfInvalidValue()
    {
        $this->expectException(\Oro\Component\Layout\Exception\UnexpectedTypeException::class);
        $builder = new StringOptionValueBuilder();
        $builder->remove(123);
    }

    public function testReplaceThrowsExceptionIfInvalidOldValue()
    {
        $this->expectException(\Oro\Component\Layout\Exception\UnexpectedTypeException::class);
        $builder = new StringOptionValueBuilder();
        $builder->replace(123, 'new');
    }

    public function testReplaceThrowsExceptionIfInvalidNewValue()
    {
        $this->expectException(\Oro\Component\Layout\Exception\UnexpectedTypeException::class);
        $builder = new StringOptionValueBuilder();
        $builder->replace('old', 123);
    }

    public function testReplaceWithNullNewValue()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('val1');
        $builder->add('val2');
        $builder->replace('val1', null);
        $this->assertEquals('val2', $builder->get());
    }

    public function testAddWithEmptyValue()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('');
        $this->assertEquals('', $builder->get());
    }

    public function testRemoveWithEmptyValue()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('val1');
        $builder->remove('');
        $this->assertEquals('val1', $builder->get());
    }

    public function testReplaceWithEmptyOldValue()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('val1');
        $builder->remove('', 'new');
        $this->assertEquals('val1', $builder->get());
    }

    public function testReplaceWithEmptyNewValue()
    {
        $builder = new StringOptionValueBuilder();
        $builder->add('val1');
        $builder->add('val2');
        $builder->remove('val1', '');
        $this->assertEquals('val2', $builder->get());
    }
}
