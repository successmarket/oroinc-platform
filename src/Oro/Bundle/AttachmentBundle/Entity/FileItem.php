<?php

namespace Oro\Bundle\AttachmentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\AttachmentBundle\Model\ExtendFileItem;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\FormBundle\Entity\EmptyItem;

/**
 * Entity for Multiple Files and Multiple Images relations
 *
 * @ORM\Table(name="oro_attachment_file_item")
 * @ORM\Entity()
 * @Config
 */
class FileItem extends ExtendFileItem implements EmptyItem
{
    /**
     * @var int|null
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var File|null
     *
     * @ORM\OneToOne(
     *     targetEntity="Oro\Bundle\AttachmentBundle\Entity\File",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     *  )
     * @ORM\JoinColumn(name="file_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $file;

    /**
     * @var int|null
     *
     * @ORM\Column(name="sort_order", type="integer", options={"default"=0})
     */
    protected $sortOrder = 0;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return null === $this->getFile();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param File|null $file
     * @return $this
     */
    public function setFile(?File $file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @return File|null
     */
    public function getFile(): ?File
    {
        return $this->file;
    }

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    /**
     * @param int|null $order
     * @return $this
     */
    public function setSortOrder(?int $order)
    {
        $this->sortOrder = $order;

        return $this;
    }
}
