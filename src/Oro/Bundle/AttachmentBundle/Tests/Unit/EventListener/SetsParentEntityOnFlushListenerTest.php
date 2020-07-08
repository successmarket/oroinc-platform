<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Oro\Bundle\AttachmentBundle\EventListener\SetsParentEntityOnFlushListener;
use Oro\Bundle\AttachmentBundle\Tests\Unit\Stub\Entity\TestEntity1;
use Oro\Bundle\AttachmentBundle\Tests\Unit\Stub\ParentEntity;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Component\PropertyAccess\PropertyAccessor;

class SetsParentEntityOnFlushListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var PropertyAccessor */
    private $propertyAccessor;

    /** @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject */
    private $configManager;

    /** @var SetsParentEntityOnFlushListener */
    private $listener;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->propertyAccessor = new PropertyAccessor();
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->listener = new SetsParentEntityOnFlushListener($this->propertyAccessor, $this->configManager);
    }

    public function testOnFlushWhenCompositeId(): void
    {
        $eventOnFlush = $this->createMock(OnFlushEventArgs::class);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventOnFlush);

        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([new \stdClass()]);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('hasMetadataFor')
            ->with(File::class)
            ->willReturn(true);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(File::class)
            ->willReturn($classMetadata);

        $entityManager
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $entityManager
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $classMetadata
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn(['id', 'name']);

        $unitOfWork
            ->expects(self::never())
            ->method('recomputeSingleEntityChangeSet');

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledCollectionUpdates')
            ->willReturn([]);

        $this->listener->onFlush($eventOnFlush);
    }

    public function testOnFlush(): void
    {
        $eventOnFlush = $this->createMock(OnFlushEventArgs::class);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventOnFlush);

        $fileToInsert = (new File())->setFilename('sample-filename');
        $fileToInsert2 = (new File())->setFilename('sample-filename2');
        $fileNotForUpdate = (new File())->setParentEntityClass($parentEntityClass = \stdClass::class);

        $entityToUpdate = $this->createEntity($id = 1, $fileToInsert, [$fileToInsert2]);
        $entityWithoutFileToUpdate = $this->createEntity(2, $fileNotForUpdate, []);
        $entityWithoutFileField = $this->createEntity(3, null, []);

        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([$entityToUpdate, $entityWithoutFileToUpdate, $entityWithoutFileField]);
        $classMetadata = $this->createMock(ClassMetadata::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('hasMetadataFor')
            ->with(File::class)
            ->willReturn(true);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(File::class)
            ->willReturn($classMetadata);
        $entityManager
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);
        $entityManager
            ->method('getClassMetadata')
            ->willReturn($classMetadata);
        $classMetadata
            ->expects($this->exactly(6))
            ->method('getIdentifier')
            ->willReturn(['id']);
        $classMetadata
            ->expects($this->exactly(3))
            ->method('getAssociationMappings')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'isOwningSide' => true,
                        'targetEntity' => File::class,
                        'fieldName' => $fieldName = 'file',
                        'type' => ClassMetadata::MANY_TO_ONE,
                    ],
                    [
                        'isOwningSide' => true,
                        'targetEntity' => File::class,
                        'fieldName' => $fieldNameToMany = 'files',
                        'type' => ClassMetadata::ONE_TO_MANY,
                    ],
                ],
                [
                    [
                        'isOwningSide' => true,
                        'targetEntity' => File::class,
                        'fieldName' => 'file',
                        'type' => ClassMetadata::MANY_TO_ONE,
                    ],
                ],
                [
                    [
                        'isOwningSide' => true,
                        'targetEntity' => File::class,
                        'fieldName' => 'file',
                        'type' => ClassMetadata::MANY_TO_ONE,
                    ],
                ],
                [
                    [
                        'isOwningSide' => false,
                        'targetEntity' => FileItem::class,
                        'fieldName' => $fieldNameToMany = 'files',
                        'type' => ClassMetadata::ONE_TO_MANY,
                    ],
                ]
            );
        $unitOfWork
            ->expects(self::exactly(2))
            ->method('recomputeSingleEntityChangeSet');
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledCollectionUpdates')
            ->willReturn([]);

        $this->listener->onFlush($eventOnFlush);

        self::assertEquals($id, $fileToInsert->getParentEntityId());
        self::assertEquals(get_class($entityToUpdate), $fileToInsert->getParentEntityClass());
        self::assertEquals($fieldName, $fileToInsert->getParentEntityFieldName());
        self::assertEquals($id, $fileToInsert2->getParentEntityId());
        self::assertEquals(get_class($entityToUpdate), $fileToInsert2->getParentEntityClass());
        self::assertEquals($fieldNameToMany, $fileToInsert2->getParentEntityFieldName());
        self::assertEquals($parentEntityClass, $fileNotForUpdate->getParentEntityClass());
    }

    public function testOnFlushCollectionsWithoutCollection(): void
    {
        $eventOnFlush = $this->createMock(OnFlushEventArgs::class);

        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventOnFlush);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('hasMetadataFor')
            ->with(File::class)
            ->willReturn(true);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(File::class)
            ->willReturn($classMetadata);

        $entityManager
            ->expects(self::once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledCollectionUpdates')
            ->willReturn([]);

        $this->configManager
            ->expects(self::never())
            ->method('getConfigs');

        $unitOfWork
            ->expects(self::never())
            ->method('recomputeSingleEntityChangeSet');

        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->listener->onFlush($eventOnFlush);
    }

    public function testOnFlushCollections(): void
    {
        $eventOnFlush = $this->createMock(OnFlushEventArgs::class);

        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventOnFlush);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->expects($this->once())
            ->method('hasMetadataFor')
            ->with(File::class)
            ->willReturn(true);
        $metadataFactory->expects($this->once())
            ->method('getMetadataFor')
            ->with(File::class)
            ->willReturn($classMetadata);

        $entityManager
            ->expects(self::once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $file1 = new File();
        $items1 = new ArrayCollection([
            (new FileItem())->setFile($file1),
        ]);

        $file2 = new File();
        $items2 = new ArrayCollection([
            (new FileItem())->setFile($file2),
        ]);

        $entity1 = new TestEntity1();
        $entity1->id = 1;

        $collection1 = new PersistentCollection($entityManager, FileItem::class, $items1);
        $collection1->setOwner($entity1, ['inversedBy' => null, 'mappedBy' => FileItem::class]);
        $collection2 = new PersistentCollection($entityManager, FileItem::class, $items2);
        $collection2->setOwner($entity1, ['inversedBy' => null, 'mappedBy' => FileItem::class]);

        $entity1->multiFileField = $collection1;
        $entity1->multiImageField = $collection2;

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledCollectionUpdates')
            ->willReturn([
                $collection1,
                $collection2,
            ]);

        $entityManager
            ->method('getClassMetadata')
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->method('getIdentifier')
            ->willReturn(['id']);

        $this->configManager
            ->expects(self::exactly(2))
            ->method('getConfigs')
            ->with('extend', TestEntity1::class)
            ->willReturn([
                new Config(new FieldConfigId('extend', TestEntity1::class, 'fieldName', 'fieldType')),
                new Config(new FieldConfigId('extend', TestEntity1::class, 'multiFileField', 'multiFile')),
                new Config(new FieldConfigId('extend', TestEntity1::class, 'multiImageField', 'multiImage')),
            ]);

        $unitOfWork
            ->expects(self::exactly(2))
            ->method('recomputeSingleEntityChangeSet')
            ->withConsecutive(
                [$classMetadata, $file1],
                [$classMetadata, $file2]
            );

        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->listener->onFlush($eventOnFlush);

        $this->assertEquals(TestEntity1::class, $file1->getParentEntityClass());
        $this->assertEquals(1, $file1->getParentEntityId());
        $this->assertEquals('multiFileField', $file1->getParentEntityFieldName());

        $this->assertEquals(TestEntity1::class, $file2->getParentEntityClass());
        $this->assertEquals(1, $file2->getParentEntityId());
        $this->assertEquals('multiImageField', $file2->getParentEntityFieldName());
    }

    public function testPrePersistPostPersist(): void
    {
        $fileToInsert = (new File())->setFilename('sample-filename');
        $fileToInsert2 = (new File())->setFilename('sample-filename2');
        $fileToInsert3 = (new File())->setFilename('sample-filename3');

        $fileItem = (new FileItem())->setFile($fileToInsert3);

        $entityToInsert = $this->createEntity($id = 1, $fileToInsert, [$fileToInsert2], [$fileItem]);

        $eventPrePersist = $this->mockLifecycleEvent($entityToInsert);
        [$entityManager] = $this->mockEntityManager($eventPrePersist);

        $entityManager
            ->method('getClassMetadata')
            ->with(\get_class($entityToInsert))
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->method('getIdentifier')
            ->willReturn(['id']);

        $classMetadata
            ->method('getAssociationMappings')
            ->willReturn([
                [
                    'isOwningSide' => true,
                    'targetEntity' => File::class,
                    'fieldName' => $fieldName = 'file',
                    'type' => ClassMetadata::MANY_TO_ONE,
                ],
                [
                    'isOwningSide' => true,
                    'targetEntity' => File::class,
                    'fieldName' => $fieldNameToMany = 'files',
                    'type' => ClassMetadata::ONE_TO_MANY,
                ],
                [
                    'isOwningSide' => false,
                    'targetEntity' => FileItem::class,
                    'fieldName' => $fieldNameImages = 'images',
                    'type' => ClassMetadata::ONE_TO_MANY,
                ],
            ]);

        $this->listener->prePersist($eventPrePersist);

        $eventPostPersist = $this->mockLifecycleEvent($entityToInsert);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventPostPersist);

        $entityManager
            ->method('getClassMetadata')
            ->withConsecutive([\get_class($entityToInsert)], [File::class])
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->method('getIdentifier')
            ->willReturn(['id']);

        $unitOfWork
            ->expects(self::exactly(3))
            ->method('scheduleExtraUpdate');

        $unitOfWork
            ->expects(self::exactly(3))
            ->method('recomputeSingleEntityChangeSet');

        $this->listener->postPersist($eventPostPersist);

        self::assertEquals($id, $fileToInsert->getParentEntityId());
        self::assertEquals(get_class($entityToInsert), $fileToInsert->getParentEntityClass());
        self::assertEquals($fieldName, $fileToInsert->getParentEntityFieldName());

        self::assertEquals($id, $fileToInsert2->getParentEntityId());
        self::assertEquals(get_class($entityToInsert), $fileToInsert2->getParentEntityClass());
        self::assertEquals($fieldNameToMany, $fileToInsert2->getParentEntityFieldName());

        self::assertEquals($id, $fileToInsert3->getParentEntityId());
        self::assertEquals(get_class($entityToInsert), $fileToInsert3->getParentEntityClass());
        self::assertEquals($fieldNameImages, $fileToInsert3->getParentEntityFieldName());

        // Checks that persist and flush will not be called again.
        $this->listener->postPersist($eventPostPersist);
    }

    public function testPrePersistPostPersistWhenNoFileToUpdate(): void
    {
        $fileNotForUpdate = (new File())->setParentEntityClass($parentEntityClass = \stdClass::class);
        $entityWithFileNotForUpdate = $this->createEntity(2, $fileNotForUpdate, []);

        $eventPrePersist = $this->mockLifecycleEvent($entityWithFileNotForUpdate);
        [$entityManager] = $this->mockEntityManager($eventPrePersist);

        $entityManager
            ->method('getClassMetadata')
            ->with(\get_class($entityWithFileNotForUpdate))
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->method('getIdentifier')
            ->willReturn(['id']);

        $classMetadata
            ->method('getAssociationMappings')
            ->willReturn([
                [
                    'isOwningSide' => true,
                    'targetEntity' => File::class,
                    'fieldName' => $fieldName = 'file',
                    'type' => ClassMetadata::MANY_TO_ONE,
                ]
            ]);

        $this->listener->prePersist($eventPrePersist);

        $eventPostPersist = $this->mockLifecycleEvent($entityWithFileNotForUpdate);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventPostPersist);

        $entityManager
            ->expects(self::never())
            ->method('getClassMetadata');

        $classMetadata
            ->expects(self::never())
            ->method('getIdentifier');

        $unitOfWork
            ->expects(self::never())
            ->method('scheduleExtraUpdate');

        $unitOfWork
            ->expects(self::never())
            ->method('recomputeSingleEntityChangeSet');

        $this->listener->postPersist($eventPostPersist);

        self::assertEquals($parentEntityClass, $fileNotForUpdate->getParentEntityClass());
    }

    public function testPrePersistPostPersistWhenNoFileField(): void
    {
        $entityWithoutFileField = $this->createEntity(2, null, []);

        $eventPrePersist = $this->mockLifecycleEvent($entityWithoutFileField);
        [$entityManager] = $this->mockEntityManager($eventPrePersist);

        $entityManager
            ->method('getClassMetadata')
            ->with(\get_class($entityWithoutFileField))
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->method('getIdentifier')
            ->willReturn(['id']);

        $classMetadata
            ->method('getAssociationMappings')
            ->willReturn([['isOwningSide' => true, 'targetEntity' => \stdClass::class]]);

        $this->listener->prePersist($eventPrePersist);

        $eventPostPersist = $this->mockLifecycleEvent($entityWithoutFileField);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventPostPersist);

        $entityManager
            ->expects(self::never())
            ->method('getClassMetadata');

        $classMetadata
            ->expects(self::never())
            ->method('getIdentifier');

        $unitOfWork
            ->expects(self::never())
            ->method('scheduleExtraUpdate');

        $unitOfWork
            ->expects(self::never())
            ->method('recomputeSingleEntityChangeSet');

        $this->listener->postPersist($eventPostPersist);
    }

    public function testPrePersistPostPersistWhenIsFileItem(): void
    {
        $entity = new FileItem();

        $eventPrePersist = $this->mockLifecycleEvent($entity);
        [$entityManager] = $this->mockEntityManager($eventPrePersist);

        $entityManager
            ->expects(self::once())
            ->method('getClassMetadata')
            ->with(get_class($entity))
            ->willReturn($classMetadata = $this->createMock(ClassMetadata::class));

        $classMetadata
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn(['id']);

        $classMetadata
            ->expects(self::once())
            ->method('getAssociationMappings')
            ->willReturn([['isOwningSide' => true, 'targetEntity' => File::class]]);

        $this->listener->prePersist($eventPrePersist);

        $eventPostPersist = $this->mockLifecycleEvent($entity);
        [$entityManager, $unitOfWork] = $this->mockEntityManager($eventPostPersist);

        $entityManager
            ->expects(self::never())
            ->method('getClassMetadata');

        $classMetadata
            ->expects(self::never())
            ->method('getIdentifier');

        $unitOfWork
            ->expects(self::never())
            ->method('scheduleExtraUpdate');

        $unitOfWork
            ->expects(self::never())
            ->method('recomputeSingleEntityChangeSet');

        $this->listener->postPersist($eventPostPersist);
    }

    /**
     * @param EventArgs|\PHPUnit\Framework\MockObject\MockObject $event
     *
     * @return array
     *  [
     *      EntityManager|\PHPUnit\Framework\MockObject\MockObject,
     *      UnitOfWork|\PHPUnit\Framework\MockObject\MockObject
     *  ]
     */
    private function mockEntityManager(\PHPUnit\Framework\MockObject\MockObject $event): array
    {
        $event
            ->method('getEntityManager')
            ->willReturn($entityManager = $this->createMock(EntityManager::class));

        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork = $this->createMock(UnitOfWork::class));

        return [$entityManager, $unitOfWork];
    }

    /**
     * @param object $entity
     *
     * @return LifecycleEventArgs|\PHPUnit\Framework\MockObject\MockObject
     */
    private function mockLifecycleEvent($entity): LifecycleEventArgs
    {
        $eventPostPersist = $this->createMock(LifecycleEventArgs::class);
        $eventPostPersist
            ->method('getEntity')
            ->willReturn($entity);

        return $eventPostPersist;
    }

    /**
     * @param int $id
     * @param File|null $file
     * @param array $files
     * @param array $images
     *
     * @return object
     */
    private function createEntity(int $id, ?File $file, array $files, array $images = [])
    {
        return new ParentEntity($id, $file, $files, $images);
    }
}
