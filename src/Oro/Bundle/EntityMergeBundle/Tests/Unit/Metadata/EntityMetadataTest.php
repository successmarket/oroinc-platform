<?php

namespace Oro\Bundle\EntityMergeBundle\Tests\Unit\Metadata;

use Oro\Bundle\EntityMergeBundle\Metadata\EntityMetadata;

class EntityMetadataTest extends \PHPUnit\Framework\TestCase
{
    const FIELD_NAME = 'fieldName';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $doctrineMetadata;

    /**
     * @var EntityMetadata
     */
    protected $metadata;

    protected function setUp(): void
    {
        $this->options = array('foo' => 'bar');
        $this->doctrineMetadata = $this->createDoctrineMetadata();
        $this->metadata = new EntityMetadata($this->options, $this->doctrineMetadata);
    }

    public function testAddFieldMetadata()
    {
        $this->assertEquals(array(), $this->metadata->getFieldsMetadata());
    }

    public function testGetDoctrineMetadata()
    {
        $this->assertEquals($this->doctrineMetadata, $this->metadata->getDoctrineMetadata());
    }

    public function testGetDoctrineMetadataFails()
    {
        $this->expectException(\Oro\Bundle\EntityMergeBundle\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Doctrine metadata is not configured.');

        $metadata = new EntityMetadata();
        $metadata->getDoctrineMetadata();
    }

    public function testFieldsMetadata()
    {
        $fieldName = 'test';
        $fieldMetadata = $this->createFieldMetadata($fieldName);

        $this->metadata->addFieldMetadata($fieldMetadata);

        $this->assertEquals(array($fieldName => $fieldMetadata), $this->metadata->getFieldsMetadata());
    }

    public function testGetClassName()
    {
        $className = 'TestEntity';

        $this->doctrineMetadata->expects($this->once())
            ->method('has')
            ->with('name')
            ->will($this->returnValue(true));

        $this->doctrineMetadata->expects($this->once())
            ->method('get')
            ->with('name')
            ->will($this->returnValue($className));

        $this->assertEquals($className, $this->metadata->getClassName());
    }

    public function testGetClassNameFails()
    {
        $this->expectException(\Oro\Bundle\EntityMergeBundle\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot get class name from merge entity metadata.');

        $this->doctrineMetadata->expects($this->once())
            ->method('has')
            ->with('name')
            ->will($this->returnValue(false));

        $this->metadata->getClassName();
    }

    protected function createDoctrineMetadata()
    {
        return $this->getMockBuilder('Oro\Bundle\EntityMergeBundle\Metadata\DoctrineMetadata')
            ->disableOriginalConstructor()->getMock();
    }

    protected function createFieldMetadata($fieldName)
    {
        $fieldMetadata = $this->getMockBuilder('Oro\Bundle\EntityMergeBundle\Metadata\FieldMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $fieldMetadata
            ->expects($this->any())
            ->method('getFieldName')
            ->will($this->returnValue($fieldName));

        return $fieldMetadata;
    }
}
