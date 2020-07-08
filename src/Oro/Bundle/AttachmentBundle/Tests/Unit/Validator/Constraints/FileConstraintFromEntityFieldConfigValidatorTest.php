<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\AttachmentBundle\Provider\FileConstraintsProvider;
use Oro\Bundle\AttachmentBundle\Validator\Constraints\FileConstraintFromEntityFieldConfig;
use Oro\Bundle\AttachmentBundle\Validator\Constraints\FileConstraintFromEntityFieldConfigValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class FileConstraintFromEntityFieldConfigValidatorTest extends \PHPUnit\Framework\TestCase
{
    private const MAX_SIZE = 1024;
    private const MIME_TYPES = ['mime/type1'];

    /** @var FileConstraintsProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $fileConstraintsProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject|FileValidator */
    private $fileValidator;

    /** @var FileConstraintFromEntityFieldConfigValidator */
    private $validator;

    protected function setUp(): void
    {
        $this->fileValidator = $this->createMock(FileValidator::class);
        $this->fileConstraintsProvider = $this->createMock(FileConstraintsProvider::class);

        $this->validator = new FileConstraintFromEntityFieldConfigValidator(
            $this->fileValidator,
            $this->fileConstraintsProvider
        );
    }

    public function testInitialize(): void
    {
        $this->fileValidator
            ->expects($this->once())
            ->method('initialize')
            ->with($context = $this->createMock(ExecutionContextInterface::class));

        $this->validator->initialize($context);
    }

    public function testValidateWhenInvalidConstraint(): void
    {
        $constraint = $this->createMock(Constraint::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expected instance of ' . FileConstraintFromEntityFieldConfig::class . ', got ' . get_class($constraint)
        );

        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testValidate(): void
    {
        $constraint = $this->createMock(FileConstraintFromEntityFieldConfig::class);
        $constraint
            ->method('getEntityClass')
            ->willReturn($entityClass = 'SampleClass');

        $constraint
            ->method('getFieldName')
            ->willReturn($fieldName = 'sampleField');

        $this->fileConstraintsProvider
            ->method('getAllowedMimeTypesForEntityField')
            ->with($entityClass, $fieldName)
            ->willReturn(self::MIME_TYPES);

        $this->fileConstraintsProvider
            ->method('getMaxSizeForEntityField')
            ->with($entityClass, $fieldName)
            ->willReturn(self::MAX_SIZE);

        $this->fileValidator
            ->expects($this->once())
            ->method('validate')
            ->with(
                $file = new \stdClass(),
                new File(
                    [
                        'mimeTypes' => self::MIME_TYPES,
                        'maxSize' => self::MAX_SIZE,
                    ]
                )
            );

        $this->validator->validate($file, $constraint);
    }
}
