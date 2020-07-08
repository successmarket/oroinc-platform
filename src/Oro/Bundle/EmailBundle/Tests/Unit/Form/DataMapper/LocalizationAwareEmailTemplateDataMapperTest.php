<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Form\DataMapper;

use Oro\Bundle\EmailBundle\Entity\EmailTemplate;
use Oro\Bundle\EmailBundle\Entity\EmailTemplateTranslation;
use Oro\Bundle\EmailBundle\Form\DataMapper\LocalizationAwareEmailTemplateDataMapper;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Component\Testing\Unit\EntityTrait;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormInterface;

class LocalizationAwareEmailTemplateDataMapperTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /** @var FormInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $translationsForm;

    /** @var FormInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $anotherForm;

    /** @var iterable */
    private $forms;

    /** @var DataMapperInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $innerDataMapper;

    /** @var LocalizationAwareEmailTemplateDataMapper */
    private $dataMapper;

    protected function setUp(): void
    {
        $this->translationsForm = $this->createMock(FormInterface::class);
        $this->translationsForm->expects($this->any())->method('getName')->willReturn('translations');

        $this->anotherForm = $this->createMock(FormInterface::class);
        $this->anotherForm->expects($this->any())->method('getName')->willReturn('another_form');
        $this->anotherForm->expects($this->never())->method('setData');
        $this->anotherForm->expects($this->never())->method('getData');

        $this->forms = new \ArrayIterator([
            $this->anotherForm,
            $this->translationsForm,
        ]);

        $this->innerDataMapper = $this->createMock(DataMapperInterface::class);

        $this->dataMapper = new LocalizationAwareEmailTemplateDataMapper($this->innerDataMapper);
    }

    public function testMapDataToFormsWithNullData(): void
    {
        $this->translationsForm->expects($this->never())->method('getName');
        $this->anotherForm->expects($this->never())->method('getName');
        $this->innerDataMapper->expects($this->never())->method('mapFormsToData');

        $this->dataMapper->mapDataToForms(null, $this->forms);
    }

    public function testMapDataToFormsWithIncorrectData(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage(
            'Expected argument of type "Oro\Bundle\EmailBundle\Entity\EmailTemplate", "array" given'
        );

        $this->dataMapper->mapDataToForms([], $this->forms);
    }

    public function testMapDataToFormsWithValidData(): void
    {
        /** @var Localization $existLocalization */
        $existLocalization = $this->getEntity(Localization::class, ['id' => 42]);
        $existTemplateLocalization = $this->getEmailTemplateTranslation($existLocalization, 'Exist localization');

        $emailTemplate = (new EmailTemplate())
            ->setSubject('Default subject')
            ->setContent('Default content')
            ->addTranslation($existTemplateLocalization);

        $this->translationsForm->expects($this->once())
            ->method('setData')
            ->with([
                'default' => (new EmailTemplateTranslation())
                    ->setSubject('Default subject')
                    ->setContent('Default content'),
                $existLocalization->getId() => $existTemplateLocalization,
            ]);

        $this->innerDataMapper->expects($this->once())
            ->method('mapDataToForms')
            ->with($emailTemplate, new \ArrayIterator([$this->anotherForm]));

        $this->dataMapper->mapDataToForms($emailTemplate, $this->forms);
    }

    public function testMapFormsToDataWithNullData(): void
    {
        $this->translationsForm->expects($this->never())->method('getName');
        $this->anotherForm->expects($this->never())->method('getName');
        $this->innerDataMapper->expects($this->never())->method('mapFormsToData');

        $data = null;
        $this->dataMapper->mapFormsToData($this->forms, $data);
    }

    public function testMapFormsToDataWithIncorrectData(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        $this->expectExceptionMessage(
            'Expected argument of type "Oro\Bundle\EmailBundle\Entity\EmailTemplate", "array" given'
        );

        $data = [];
        $this->dataMapper->mapFormsToData($this->forms, $data);
    }

    public function testMapFormsToDataWithValidData(): void
    {
        /** @var Localization $newLocalization */
        $newLocalization = $this->getEntity(Localization::class, ['id' => 28]);
        $newTemplateLocalizationData = $this->getEmailTemplateTranslation($newLocalization, 'New localization');

        /** @var Localization $existLocalization */
        $existLocalization = $this->getEntity(Localization::class, ['id' => 42]);
        $existTemplateLocalizationData = $this->getEmailTemplateTranslation(
            $existLocalization,
            'Exist localization updated'
        );

        $this->translationsForm->expects($this->once())
            ->method('getData')
            ->willReturn([
                'default' => (new EmailTemplateTranslation())
                    ->setSubject('Default subject')
                    ->setContent('Default content'),
                $newLocalization->getId() => $newTemplateLocalizationData,
                $existLocalization->getId() => $existTemplateLocalizationData,
            ]);

        $existTemplateLocalization = $this->getEmailTemplateTranslation(
            $existLocalization,
            'Exist localization',
            true,
            true
        );

        $emailTemplate = new EmailTemplate();
        $emailTemplate->addTranslation($existTemplateLocalization);
        $existTemplateLocalizationData->setTemplate($emailTemplate);

        $this->innerDataMapper->expects($this->once())
            ->method('mapFormsToData')
            ->with(new \ArrayIterator([$this->anotherForm]), $emailTemplate);

        $this->dataMapper->mapFormsToData($this->forms, $emailTemplate);

        $this->assertEquals('Default subject', $emailTemplate->getSubject());
        $this->assertEquals('Default content', $emailTemplate->getContent());

        $this->assertTrue($emailTemplate->getTranslations()->contains($newTemplateLocalizationData));
        $this->assertTrue($emailTemplate->getTranslations()->contains($existTemplateLocalization));

        $this->assertEquals($existTemplateLocalizationData, $existTemplateLocalization);
    }

    /**
     * @param Localization $localization
     * @param string $data
     * @param bool $subjectFallback
     * @param bool $contentFallback
     * @return EmailTemplateTranslation
     */
    private function getEmailTemplateTranslation(
        Localization $localization,
        string $data,
        bool $subjectFallback = false,
        bool $contentFallback = false
    ): EmailTemplateTranslation {
        return (new EmailTemplateTranslation())
            ->setLocalization($localization)
            ->setSubject($data . ' subject')
            ->setSubjectFallback($subjectFallback)
            ->setContent($data . ' content')
            ->setSubjectFallback($contentFallback);
    }
}
