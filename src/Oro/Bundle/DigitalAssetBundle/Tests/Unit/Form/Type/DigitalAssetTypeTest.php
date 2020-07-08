<?php

namespace Oro\Bundle\DigitalAssetBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\DigitalAssetBundle\Entity\DigitalAsset;
use Oro\Bundle\DigitalAssetBundle\Form\Type\DigitalAssetType;
use Oro\Bundle\FormBundle\Form\Extension\DataBlockExtension;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizationCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedPropertyType;
use Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub\LocalizationCollectionTypeStub;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class DigitalAssetTypeTest extends FormIntegrationTestCase
{
    use EntityTrait;

    private const SAMPLE_TITLE = 'sample title';

    /** @var TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $translator;

    /** @var DigitalAssetType */
    private $formType;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->formType = new DigitalAssetType($this->translator);

        parent::setUp();
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertEquals('oro_digital_asset', $this->formType->getBlockPrefix());
    }

    public function testConfigureOptions(): void
    {
        $this->translator
            ->method('trans')
            ->with('oro.digitalasset.controller.sections.general.label')
            ->willReturn($translatedTitle = 'translatedTitle');

        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with(
                [
                    'data_class' => DigitalAsset::class,
                    'block_config' => [
                        'general' => [
                            'title' => $translatedTitle,
                        ],
                    ],
                    'validation_groups' => ['Default', 'DigitalAsset'],
                ]
            );

        $this->formType->configureOptions($resolver);
    }

    /**
     * @dataProvider submitDataProvider
     *
     * @param DigitalAsset $defaultData
     * @param array $submittedData
     * @param DigitalAsset $expectedData
     */
    public function testSubmit(DigitalAsset $defaultData, array $submittedData, DigitalAsset $expectedData): void
    {
        $form = $this->factory->create(DigitalAssetType::class, $defaultData);

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $form->submit($submittedData);

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($expectedData->getTitles(), $form->getData()->getTitles());
        $this->assertEquals($expectedData->getSourceFile()->getFile(), $form->getData()->getSourceFile()->getFile());
        $this->assertInstanceOf(\DateTime::class, $form->getData()->getSourceFile()->getUpdatedAt());
    }

    /**
     * @return array
     */
    public function submitDataProvider(): array
    {
        $sourceFile = new File();
        $sourceFile->setFile($file = new SymfonyFile('sample-path', false));

        return [
            'title is set, source file is uploaded' => [
                'defaultData' => new DigitalAsset(),
                'submittedData' => [
                    'titles' => ['values' => ['default' => self::SAMPLE_TITLE]],
                    'sourceFile' => ['file' => $file],
                ],
                'expectedData' => (new DigitalAsset())
                    ->addTitle((new LocalizedFallbackValue())->setString(self::SAMPLE_TITLE))
                    ->setSourceFile($sourceFile),
            ],
            'title is updated, source file is not required when digital asset is not new' => [
                'defaultData' => $this->getEntity(
                    DigitalAsset::class,
                    ['id' => 1, 'sourceFile' => (new File())->setUpdatedAt(new \DateTime()),]
                ),
                'submittedData' => [
                    'titles' => ['values' => ['default' => self::SAMPLE_TITLE]],
                    'sourceFile' => ['file' => null],
                ],
                'expectedData' => (new DigitalAsset())
                    ->addTitle((new LocalizedFallbackValue())->setString(self::SAMPLE_TITLE))
                    ->setSourceFile(new File()),
            ],
        ];
    }

    public function testSubmitWhenNoFile(): void
    {
        $form = $this->factory->create(DigitalAssetType::class, $defaultData = new DigitalAsset());

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $form->submit([
            'titles' => ['values' => ['default' => self::SAMPLE_TITLE]],
            'sourceFile' => ['file' => null],
        ]);

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->isSynchronized());
        static::assertStringContainsString('This value should not be blank', (string)$form->getErrors(true, false));
    }

    public function testSubmitWhenNoTitle(): void
    {
        $form = $this->factory->create(DigitalAssetType::class, $defaultData = new DigitalAsset());

        $this->assertEquals($defaultData, $form->getData());
        $this->assertEquals($defaultData, $form->getViewData());

        $sourceFile = new File();
        $sourceFile->setFile($file = new SymfonyFile('sample-path', false));

        $form->submit([
            'titles' => ['values' => ['default' => '']],
            'sourceFile' => ['file' => $sourceFile],
        ]);

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->isSynchronized());
        static::assertStringContainsString('This value should not be blank', (string)$form->getErrors(true, false));
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtensions(): array
    {
        $doctrine = $this->createMock(ManagerRegistry::class);

        return array_merge(
            parent::getExtensions(),
            [
                new PreloadedExtension(
                    [
                        DigitalAssetType::class => $this->formType,
                        LocalizedFallbackValueCollectionType::class => new LocalizedFallbackValueCollectionType(
                            $doctrine
                        ),
                        LocalizedPropertyType::class => new LocalizedPropertyType(),
                        LocalizationCollectionType::class => new LocalizationCollectionTypeStub(),
                    ],
                    []
                ),
                new ValidatorExtension(Validation::createValidator()),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTypeExtensions(): array
    {
        return array_merge(
            parent::getExtensions(),
            [
                new DataBlockExtension(),
                new FormTypeHttpFoundationExtension(),
            ]
        );
    }
}
