<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Field;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Field\RelatedEntityStateHelper;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\TestUtils\ORM\Mocks\UnitOfWork;

class RelatedEntityStateHelperTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /**
     * @var FieldHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $fieldHelper;

    /**
     * @var DoctrineHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $doctrineHelper;

    /**
     * @var RelatedEntityStateHelper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->fieldHelper = $this->createMock(FieldHelper::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->helper = new RelatedEntityStateHelper(
            $this->fieldHelper,
            $this->doctrineHelper
        );
    }

    public function testForgetLoadedCollectionItems()
    {
        $businessUnit = $this->getEntity(BusinessUnit::class, ['id' => 1]);

        /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $this->createMock(ClassMetadata::class);
        /** @var PersistentCollection $collection */
        $collection = new PersistentCollection($em, $classMetadata, new ArrayCollection([$businessUnit]));
        /** @var Organization $organization */
        $organization = $this->getEntity(Organization::class);
        $collection->setOwner(
            $organization,
            [
                'targetEntity' => BusinessUnit::class,
                'inversedBy' => 'organization',
                'isOwningSide' => false,
                'type' => ClassMetadata::ONE_TO_MANY
            ]
        );
        $collection->takeSnapshot();
        $organization->setBusinessUnits($collection);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('getName')
            ->willReturn(Organization::class);
        $metadata->expects($this->any())
            ->method('getAssociationMapping')
            ->withConsecutive(
                ['businessUnits'],
                ['organizations']
            )
            ->willReturnOnConsecutiveCalls(
                ['isCascadeDetach' => true],
                ['isCascadeDetach' => false]
            );
        $this->doctrineHelper->expects($this->once())
            ->method('getEntityMetadata')
            ->with($organization)
            ->willReturn($metadata);

        $this->fieldHelper->expects($this->once())
            ->method('getRelations')
            ->with(Organization::class, true)
            ->willReturn([
                ['name' => 'businessUnits'],
                ['name' => 'organizations']
            ]);

        $this->fieldHelper->expects($this->once())
            ->method('isMultipleRelation')
            ->willReturn(true);

        $this->fieldHelper->expects($this->any())
            ->method('getObjectValue')
            ->willReturnCallback(function ($object, $field) {
                return $this->getPropertyAccessor()->getValue($object, $field);
            });

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityManager')
            ->with($businessUnit)
            ->willReturn($em);
        $uow = $this->createMock(UnitOfWork::class);
        $em->expects($this->once())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $uow->expects($this->once())
            ->method('removeFromIdentityMap')
            ->with($businessUnit);

        $this->helper->rememberAlteredCollectionsItems($organization);
        $this->helper->revertRelations();

        $this->assertFalse($organization->getBusinessUnits()->contains($businessUnit));
    }

    public function testRemoveRememberedCollectionItems()
    {
        /** @var Organization $organization */
        $organization = $this->getEntity(Organization::class);
        /** @var BusinessUnit $businessUnit */
        $businessUnit = $this->getEntity(BusinessUnit::class, ['id' => 1]);
        $organization->addBusinessUnit($businessUnit);

        $this->fieldHelper->expects($this->any())
            ->method('getObjectValue')
            ->willReturnCallback(function ($object, $field) {
                return $this->getPropertyAccessor()->getValue($object, $field);
            });

        $this->helper->rememberCollectionRelation($organization, 'businessUnits', $businessUnit);
        $this->helper->revertRelations();
        $this->assertFalse($organization->getBusinessUnits()->contains($businessUnit));
    }
}
