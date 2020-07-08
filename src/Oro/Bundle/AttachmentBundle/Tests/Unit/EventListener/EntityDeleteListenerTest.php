<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\EventListener;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\EventListener\EntityDeleteListener;
use Oro\Bundle\AttachmentBundle\Tests\Unit\Stub\FileAwareEntityStub;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Component\TestUtils\ORM\Mocks\UnitOfWork;

class EntityDeleteListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var DoctrineHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrineHelper;

    /** @var EntityDeleteListener */
    private $listener;

    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->doctrineHelper->expects($this->any())
            ->method('getEntityClass')
            ->willReturnCallback(
                static function ($entity): string {
                    return \get_class($entity);
                }
            );

        $this->doctrineHelper->expects($this->any())
            ->method('getSingleEntityIdentifier')
            ->willReturnCallback(
                static function ($entity): ?int {
                    return \method_exists($entity, 'getId') ? $entity->getId() : null;
                }
            );

        $this->listener = new EntityDeleteListener($this->doctrineHelper);
    }

    public function testOnFlush(): void
    {
        $file = new File();
        $image = new File();

        $entity1 = new FileAwareEntityStub();
        $entity1->setId(1001)->setFile($file);

        $entity2 = new FileAwareEntityStub();
        $entity2->setImage($image);

        $entity3 = new FileAwareEntityStub();
        $entity3->setId(3003)->setString('test');

        $uow = new UnitOfWork();
        $uow->addDeletion($entity1);
        $uow->addDeletion($entity2);
        $uow->addDeletion($entity3);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->exactly(2))
            ->method('findBy')
            ->willReturnMap(
                [
                    [
                        ['parentEntityClass' => FileAwareEntityStub::class, 'parentEntityId' => $entity1->getId()],
                        null,
                        null,
                        null,
                        [$entity1->getFile()]
                    ],
                    [
                        ['parentEntityClass' => FileAwareEntityStub::class, 'parentEntityId' => $entity3->getId()],
                        null,
                        null,
                        null,
                        []
                    ],
                ]
            );

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->any())
            ->method('getRepository')
            ->with(File::class)
            ->willReturn($repository);
        $manager->expects($this->once())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $manager->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($file));

        $this->doctrineHelper->expects($this->any())
            ->method('getEntityManagerForClass')
            ->with(File::class)
            ->willReturn($manager);

        $this->listener->onFlush();
    }
}
