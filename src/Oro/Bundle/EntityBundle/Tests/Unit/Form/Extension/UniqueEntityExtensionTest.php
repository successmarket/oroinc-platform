<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Form\Extension;

use Oro\Bundle\EntityBundle\Form\Extension\UniqueEntityExtension;

class UniqueEntityExtensionTest extends \PHPUnit\Framework\TestCase
{
    const ENTITY = 'Namespace\EntityName';

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $configProvider;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $config;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $validator;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $validatorMetadata;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $builder;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $doctrineHelper;

    /**
     * @var UniqueEntityExtension
     */
    protected $extension;

    protected function setUp(): void
    {
        $metadata = $this->getMockBuilder('\Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->validator = $this->createMock('Symfony\Component\Validator\Validator\ValidatorInterface');

        $translator = $this->createMock('Symfony\Component\Translation\Translator');

        $this->configProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->config = $this
            ->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigInterface')
            ->getMock();

        $this->validatorMetadata = $this
            ->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->builder = $this
            ->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(self::ENTITY));

        $this->extension = new UniqueEntityExtension(
            $this->validator,
            $translator,
            $this->configProvider,
            $this->doctrineHelper
        );
    }

    public function testWithoutClass()
    {
        $this->validatorMetadata
            ->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, []);
    }

    public function testForNotManageableEntity()
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(false);

        $this->configProvider->expects($this->never())
            ->method('hasConfig');

        $this->validatorMetadata->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithoutConfig()
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider
            ->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->will($this->returnValue(false));

        $this->validatorMetadata
            ->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithoutUniqueKeyOption()
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider
            ->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->will($this->returnValue(true));

        $this->configProvider
            ->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY)
            ->will($this->returnValue($this->config));

        $this->validatorMetadata
            ->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithConfigAndKeys()
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider
            ->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->will($this->returnValue(true));

        $this->configProvider
            ->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($this->config));

        $this->config
            ->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($param) {
                        $data = [
                            'label'      => 'label',
                            'unique_key' => ['keys' => ['tag0' => ['name' => 'test', 'key' => ['field']]]]
                        ];

                        return $data[$param];
                    }
                )
            );

        $this->validator
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(self::ENTITY)
            ->will($this->returnValue($this->validatorMetadata));

        $this->validatorMetadata
            ->expects($this->once())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }
}
