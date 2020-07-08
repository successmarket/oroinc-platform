<?php

namespace Oro\Bundle\DigitalAssetBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\DigitalAssetBundle\Entity\DigitalAsset;
use Oro\Bundle\DigitalAssetBundle\Reflector\FileReflector;

/**
 * Tracks DigitalAsset source changes and updates child entity Files as well as triggers images resizing event
 */
class DigitalAssetSourceChangedListener
{
    /** @var FileReflector */
    private $fileReflector;

    /**
     * @param FileReflector $fileReflector
     */
    public function __construct(FileReflector $fileReflector)
    {
        $this->fileReflector = $fileReflector;
    }

    /**
     * @param File $file
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(File $file, LifecycleEventArgs $args): void
    {
        if ($file->getParentEntityClass() !== DigitalAsset::class) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $digitalAssetRepository = $entityManager->getRepository(DigitalAsset::class);

        $childFiles = $digitalAssetRepository->findChildFilesByDigitalAssetId($file->getParentEntityId());
        foreach ($childFiles as $childFile) {
            $this->fileReflector->reflectFromFile($childFile, $file);
        }

        $entityManager->flush();
    }
}
