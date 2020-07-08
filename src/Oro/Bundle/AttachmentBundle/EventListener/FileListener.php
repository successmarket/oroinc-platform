<?php

namespace Oro\Bundle\AttachmentBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Manager\FileManager;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

/**
 * Listens on File lifecycle events to handle its upload.
 */
class FileListener
{
    /** @var FileManager */
    protected $fileManager;

    /** @var TokenAccessorInterface */
    protected $tokenAccessor;

    /**
     * @param FileManager $fileManager
     * @param TokenAccessorInterface $tokenAccessor
     */
    public function __construct(FileManager $fileManager, TokenAccessorInterface $tokenAccessor)
    {
        $this->fileManager = $fileManager;
        $this->tokenAccessor = $tokenAccessor;
    }

    /**
     * @param File               $entity
     * @param LifecycleEventArgs $args
     */
    public function prePersist(File $entity, LifecycleEventArgs $args)
    {
        $entityManager = $args->getEntityManager();

        if ($entity->isEmptyFile() && $entityManager->contains($entity)) {
            // Skips updates if file is going to be deleted.
            $entityManager->getUnitOfWork()->clearEntityChangeSet(spl_object_hash($entity));

            $entityManager->refresh($entity);
            $entity->setEmptyFile(true);

            return;
        }

        $this->fileManager->preUpload($entity);
        $file = $entity->getFile();
        if (null !== $file && $file->isFile() && !$entity->getOwner()) {
            $entity->setOwner($this->tokenAccessor->getUser());
        }
    }

    /**
     * @param File               $entity
     * @param LifecycleEventArgs $args
     */
    public function preUpdate(File $entity, LifecycleEventArgs $args)
    {
        $this->prePersist($entity, $args);
    }

    /**
     * @param File               $entity
     * @param LifecycleEventArgs $args
     */
    public function postPersist(File $entity, LifecycleEventArgs $args)
    {
        $entityManager = $args->getEntityManager();

        // Delete File if it is marked for deletion and new file is not provided.
        if ($entity->isEmptyFile() && !$entity->getFile()) {
            $entityManager->remove($entity);
        } else {
            $this->fileManager->upload($entity);
        }
    }

    /**
     * @param File               $entity
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(File $entity, LifecycleEventArgs $args)
    {
        $this->postPersist($entity, $args);
    }
}
