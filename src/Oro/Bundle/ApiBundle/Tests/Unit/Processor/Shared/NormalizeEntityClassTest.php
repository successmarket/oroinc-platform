<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Shared;

use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Processor\Shared\NormalizeEntityClass;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetList\GetListProcessorTestCase;
use Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException;

class NormalizeEntityClassTest extends GetListProcessorTestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|ValueNormalizer */
    private $valueNormalizer;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ResourcesProvider */
    private $resourcesProvider;

    /** @var NormalizeEntityClass */
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->valueNormalizer = $this->createMock(ValueNormalizer::class);
        $this->resourcesProvider = $this->createMock(ResourcesProvider::class);

        $this->processor = new NormalizeEntityClass(
            $this->valueNormalizer,
            $this->resourcesProvider
        );
    }

    public function testProcessWhenClassIsNotSet()
    {
        $this->processor->process($this->context);

        self::assertNull($this->context->getClassName());
        self::assertEquals(
            [
                Error::createValidationError(
                    'entity type constraint',
                    'The entity class must be set in the context.'
                )
            ],
            $this->context->getErrors()
        );
    }

    public function testProcessWhenClassIsEmpty()
    {
        $this->context->setClassName('');
        $this->processor->process($this->context);

        self::assertSame('', $this->context->getClassName());
        self::assertEquals(
            [
                Error::createValidationError(
                    'entity type constraint',
                    'The entity class must be set in the context.'
                )
            ],
            $this->context->getErrors()
        );
    }

    public function testProcessWhenClassAlreadyNormalized()
    {
        $this->valueNormalizer->expects(self::never())
            ->method('normalizeValue');

        $this->context->setClassName('Test\Class');
        $this->processor->process($this->context);
    }

    public function testProcess()
    {
        $entityType = 'test';

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with($entityType, DataType::ENTITY_CLASS, $this->context->getRequestType())
            ->willReturn('Test\Class');
        $this->resourcesProvider->expects(self::once())
            ->method('isResourceAccessible')
            ->with('Test\Class', $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn(true);

        $this->context->setClassName($entityType);
        $this->processor->process($this->context);

        self::assertSame('Test\Class', $this->context->getClassName());
    }

    public function testProcessForNotAccessibleEntityType()
    {
        $this->expectException(\Oro\Bundle\ApiBundle\Exception\ResourceNotAccessibleException::class);

        $entityType = 'test';

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with($entityType, DataType::ENTITY_CLASS, $this->context->getRequestType())
            ->willReturn('Test\Class');
        $this->resourcesProvider->expects(self::once())
            ->method('isResourceAccessible')
            ->with('Test\Class', $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn(false);

        $this->context->setClassName($entityType);
        $this->processor->process($this->context);
    }

    public function testProcessForInvalidEntityType()
    {
        $entityType = 'test';

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with($entityType, DataType::ENTITY_CLASS, $this->context->getRequestType())
            ->willThrowException(new EntityAliasNotFoundException($entityType));

        $this->context->setClassName($entityType);
        $this->processor->process($this->context);

        self::assertNull($this->context->getClassName());
        self::assertEquals(
            [
                Error::createValidationError('entity type constraint', 'Unknown entity type: test.', 404)
            ],
            $this->context->getErrors()
        );
    }
}
