<?php

namespace Oro\Bundle\EmailBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EmailBundle\Model\ExtendEmailTemplateTranslation;
use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;
use Oro\Bundle\LocaleBundle\Entity\Localization;

/**
 * Represents translations for email templates.
 *
 * @ORM\Entity()
 * @ORM\Table(name="oro_email_template_localized")
 * @Config()
 */
class EmailTemplateTranslation extends ExtendEmailTemplateTranslation
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var EmailTemplate|null
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\EmailBundle\Entity\EmailTemplate", inversedBy="translations")
     * @ORM\JoinColumn(name="template_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private $template;

    /**
     * @var Localization
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\LocaleBundle\Entity\Localization")
     * @ORM\JoinColumn(name="localization_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private $localization;

    /**
     * @var string|null
     *
     * @ORM\Column(name="subject", type="string", length=255, nullable=true)
     */
    private $subject;

    /**
     * @var bool
     *
     * @ORM\Column(name="subject_fallback", type="boolean", options={"default"=true})
     */
    private $subjectFallback = true;

    /**
     * @var string|null
     *
     * @ORM\Column(name="content", type="text", nullable=true)
     */
    private $content;

    /**
     * @var bool
     *
     * @ORM\Column(name="content_fallback", type="boolean", options={"default"=true})
     */
    private $contentFallback = true;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return EmailTemplate|null
     */
    public function getTemplate(): ?EmailTemplate
    {
        return $this->template;
    }

    /**
     * @param EmailTemplate|null $template
     * @return EmailTemplateTranslation
     */
    public function setTemplate(?EmailTemplate $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return Localization|null
     */
    public function getLocalization(): ?Localization
    {
        return $this->localization;
    }

    /**
     * @param Localization|null $localization
     * @return EmailTemplateTranslation
     */
    public function setLocalization(?Localization $localization): self
    {
        $this->localization = $localization;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * @param string|null $subject
     * @return EmailTemplateTranslation
     */
    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSubjectFallback(): bool
    {
        return $this->subjectFallback;
    }

    /**
     * @param bool $subjectFallback
     * @return EmailTemplateTranslation
     */
    public function setSubjectFallback(bool $subjectFallback): self
    {
        $this->subjectFallback = $subjectFallback;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return EmailTemplateTranslation
     */
    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return bool
     */
    public function isContentFallback(): bool
    {
        return $this->contentFallback;
    }

    /**
     * @param bool $contentFallback
     * @return EmailTemplateTranslation
     */
    public function setContentFallback(bool $contentFallback): self
    {
        $this->contentFallback = $contentFallback;
        return $this;
    }
}
