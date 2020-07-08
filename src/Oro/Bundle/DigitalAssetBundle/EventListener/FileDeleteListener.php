<?php

namespace Oro\Bundle\DigitalAssetBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\EventListener\FileDeleteListener as BaseFileDeleteListener;

/**
 * Listens on File lifecycle events to perform its deletion from filesystem taking into account digital asset relation.
 */
class FileDeleteListener
{
    /** @var BaseFileDeleteListener */
    private $innerFileDeleteListener;

    /**
     * @param BaseFileDeleteListener $fileDeleteListener
     */
    public function __construct(BaseFileDeleteListener $fileDeleteListener)
    {
        $this->innerFileDeleteListener = $fileDeleteListener;
    }

    /**
     * @param File $file
     * @param LifecycleEventArgs $args
     */
    public function preRemove(File $file, LifecycleEventArgs $args): void
    {
        // Initializes proxy because it will be needed in postRemove().
        $file->getDigitalAsset();
    }

    /**
     * @param File $file
     * @param LifecycleEventArgs $args
     */
    public function postRemove(File $file, LifecycleEventArgs $args): void
    {
        if (!$file->getDigitalAsset()) {
            $this->innerFileDeleteListener->postRemove($file, $args);
        }
    }

    /**
     * @param File $file
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(File $file, LifecycleEventArgs $args): void
    {
        $changeSet = $args->getEntityManager()->getUnitOfWork()->getEntityChangeSet($file);
        if (empty($changeSet['digitalAsset'][0]) && !$file->getDigitalAsset()) {
            $this->innerFileDeleteListener->postUpdate($file, $args);
        }
    }
}
