<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Attribute\Type;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Attribute\Type\OneToManyAttributeType;

class OneToManyAttributeTypeTest extends ManyToManyAttributeTypeTest
{
    /**
     * {@inheritdoc}
     */
    protected function getAttributeType()
    {
        /** @var DoctrineHelper|\PHPUnit\Framework\MockObject\MockObject $doctrineHelper */
        $doctrineHelper = $this->createMock(DoctrineHelper::class);
        $doctrineHelper->expects($this->any())
            ->method('getEntityMetadata')
            ->with(self::CLASS_NAME)
            ->willReturn($this->metadata);

        return new OneToManyAttributeType($this->entityNameResolver, $doctrineHelper);
    }
}
