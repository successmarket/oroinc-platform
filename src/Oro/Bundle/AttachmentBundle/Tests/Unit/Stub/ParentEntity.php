<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Stub;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Oro\Bundle\AttachmentBundle\Entity\File;

class ParentEntity
{
    /** @var int */
    public $id;

    /** @var File */
    public $file;

    /** @var Collection */
    public $files;

    /** @var Collection */
    public $images;

    /**
     * @param int $id
     * @param File|null $file
     * @param array $files
     * @param array $images
     */
    public function __construct(int $id, ?File $file, array $files, array $images = [])
    {
        $this->id = $id;
        $this->file = $file;
        $this->files = new ArrayCollection($files);
        $this->images = new ArrayCollection($images);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
