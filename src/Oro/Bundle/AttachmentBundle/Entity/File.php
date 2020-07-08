<?php

namespace Oro\Bundle\AttachmentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\AttachmentBundle\Model\ExtendFile;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\ConfigField;
use Oro\Bundle\SecurityBundle\Tools\UUIDGenerator;
use Symfony\Component\HttpFoundation\File\File as ComponentFile;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * File entity.
 * Contains information about uploaded file. Can be attached to any entity which requires file or image functionality.
 *
 * @ORM\Table(name="oro_attachment_file", indexes = {
 *      @ORM\Index("att_file_orig_filename_idx", columns = {"original_filename"}),
 *      @ORM\Index("att_file_uuid_idx", columns = {"uuid"})
 * })
 * @ORM\Entity(repositoryClass="Oro\Bundle\AttachmentBundle\Entity\Repository\FileRepository")
 * @ORM\HasLifecycleCallbacks()
 * @Config(
 *      defaultValues={
 *          "entity"={
 *              "icon"="fa-file"
 *          },
 *          "note"={
 *              "immutable"=true
 *          },
 *          "comment"={
 *              "immutable"=true
 *          },
 *          "activity"={
 *              "immutable"=true
 *          },
 *          "attachment"={
 *              "immutable"=true
 *          }
 *      }
 * )
 */
class File extends ExtendFile implements FileExtensionInterface, \Serializable
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="uuid", type="guid", nullable=true)
     */
    protected $uuid;

    /**
     * @var UserInterface
     *
     * @ORM\ManyToOne(targetEntity="Symfony\Component\Security\Core\User\UserInterface")
     * @ORM\JoinColumn(name="owner_user_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $owner;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    protected $filename;

    /**
     * @var string
     *
     * @ORM\Column(name="extension", type="string", length=10, nullable=true)
     */
    protected $extension;

    /**
     * @var string
     *
     * @ORM\Column(name="mime_type", type="string", length=100, nullable=true)
     */
    protected $mimeType;

    /**
     * @var string
     *
     * @ORM\Column(name="original_filename", type="string", length=255, nullable=true)
     */
    protected $originalFilename;

    /**
     * @var integer
     *
     * @ORM\Column(name="file_size", type="integer", nullable=true)
     */
    protected $fileSize;

    /**
     * Class name of the parent entity to which this file belongs. Needed in sake of ACL checks.
     *
     * @var string|null
     *
     * @ORM\Column(name="parent_entity_class", type="string", length=512, nullable=true)
     */
    protected $parentEntityClass;

    /**
     * Id of the parent entity to which this file belongs. Needed in sake of ACL checks.
     *
     * @var int|null
     *
     * @ORM\Column(name="parent_entity_id", type="integer", nullable=true)
     */
    protected $parentEntityId;

    /**
     * Field name where the file is stored in the parent entity to which it belongs. Needed in sake of ACL checks.
     *
     * @var string|null
     *
     * @ORM\Column(name="parent_entity_field_name", type="string", length=50, nullable=true)
     */
    protected $parentEntityFieldName;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @ConfigField(
     *      defaultValues={
     *          "entity"={
     *              "label"="oro.ui.created_at"
     *          }
     *      }
     * )
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     * @ConfigField(
     *      defaultValues={
     *          "entity"={
     *              "label"="oro.ui.updated_at"
     *          }
     *      }
     * )
     */
    protected $updatedAt;

    /**
     * @var ComponentFile $file
     */
    protected $file;

    /**
     * @var bool
     */
    protected $emptyFile;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string|null $uuid
     * @return File
     */
    public function setUuid(?string $uuid): File
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    /**
     * Set filename
     *
     * @param string $filename
     * @return File
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set originalFilename
     *
     * @param string $originalFilename
     * @return File
     */
    public function setOriginalFilename($originalFilename)
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    /**
     * Get originalFilename
     *
     * @return string
     */
    public function getOriginalFilename()
    {
        return $this->originalFilename;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return File
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return File
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param ComponentFile|null $file
     */
    public function setFile(ComponentFile $file = null)
    {
        $this->file = $file;
    }

    /**
     * @return ComponentFile|null
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param $extension
     * @return $this
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param bool $emptyFile
     * @return $this
     */
    public function setEmptyFile($emptyFile)
    {
        $this->emptyFile = $emptyFile;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isEmptyFile()
    {
        return $this->emptyFile;
    }

    /**
     * @param $mimeType
     * @return $this
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param $fileSize
     * @return $this
     */
    public function setFileSize($fileSize)
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * @return int
     */
    public function getFileSize()
    {
        return $this->fileSize;
    }

    /**
     * Pre persist event handler
     *
     * @ORM\PrePersist
     */
    public function prePersist()
    {
        $this->createdAt = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->updatedAt = clone $this->createdAt;

        if (!$this->uuid) {
            $this->uuid = UUIDGenerator::v4();
        }
    }

    /**
     * Pre update event handler
     *
     * @ORM\PreUpdate
     */
    public function preUpdate()
    {
        $this->updatedAt = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $fileName = (string)$this->getFilename();
        if (!$fileName) {
            return '';
        }

        return $fileName . ' (' . $this->getOriginalFilename() . ')';
    }

    /**
     * @param UserInterface|null $owningUser
     *
     * @return File
     */
    public function setOwner($owningUser)
    {
        $this->owner = $owningUser;

        return $this;
    }

    /**
     * @return UserInterface
     */
    public function getOwner()
    {
        return $this->owner;
    }

    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
        }

        // Resets uuid for clonned object, will be created on prePersist
        $this->uuid = null;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->id,
            $this->filename,
            $this->uuid,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        [
            $this->id,
            $this->filename,
            $this->uuid
        ] = unserialize($serialized);
    }

    /**
     * @param string $parentEntityClass
     *
     * @return File
     */
    public function setParentEntityClass(?string $parentEntityClass): File
    {
        $this->parentEntityClass = $parentEntityClass;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getParentEntityClass(): ?string
    {
        return $this->parentEntityClass;
    }

    /**
     * @param int $parentEntityId
     *
     * @return File
     */
    public function setParentEntityId(?int $parentEntityId): File
    {
        $this->parentEntityId = $parentEntityId;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getParentEntityId(): ?int
    {
        return $this->parentEntityId;
    }

    /**
     * @param string|null $parentEntityFieldName
     *
     * @return File
     */
    public function setParentEntityFieldName(?string $parentEntityFieldName): File
    {
        $this->parentEntityFieldName = $parentEntityFieldName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getParentEntityFieldName(): ?string
    {
        return $this->parentEntityFieldName;
    }
}
