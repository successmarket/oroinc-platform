<?php

namespace Oro\Bundle\DraftBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\DraftBundle\Model\ExtendDraftProject;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\UserBundle\Entity\Ownership\UserAwareTrait;

/**
 * Represents a Project of Drafts
 *
 * @ORM\Entity
 * @ORM\Table(name="oro_draft_project")
 * @Config(
 *      defaultValues={
 *          "ownership"={
 *              "owner_type"="USER",
 *              "owner_field_name"="owner",
 *              "owner_column_name"="user_owner_id",
 *              "organization_field_name"="organization",
 *              "organization_column_name"="organization_id"
 *          },
 *          "security"={
 *              "type"="ACL",
 *              "group_name"="",
 *          },
 *      }
 * )
 */
class DraftProject extends ExtendDraftProject implements DatesAwareInterface
{
    use DatesAwareTrait;
    use UserAwareTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string", length=255, nullable=false)
     */
    protected $title;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param string $title
     *
     * @return DraftProject
     */
    public function setTitle(string $title): DraftProject
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }
}
