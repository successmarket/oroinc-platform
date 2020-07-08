<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Form\DataTransformer\LocalizedFallbackValueCollectionTransformer;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedFallbackValueCollectionType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizedPropertyType;
use Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub\CustomLocalizedFallbackValueStub;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class LocalizedFallbackValueCollectionTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ManagerRegistry
     */
    protected $registry;

    /**
     * @var LocalizedFallbackValueCollectionType
     */
    protected $type;

    protected function setUp(): void
    {
        $this->registry = $this->createMock('Doctrine\Common\Persistence\ManagerRegistry');
        $this->type = new LocalizedFallbackValueCollectionType($this->registry);
    }

    public function testSetDefaults()
    {
        $expectedOptions = [
            'field' => 'string',
            'value_class' => LocalizedFallbackValue::class,
            'entry_type' => TextType::class,
            'entry_options' => [],
            'exclude_parent_localization' => false,
            'use_tabs' => false
        ];

        $resolver = $this->createMock('Symfony\Component\OptionsResolver\OptionsResolver');
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($expectedOptions);

        $this->type->configureOptions($resolver);
    }

    public function testBuildForm()
    {
        $type = 'form_text';
        $options = ['key' => 'value'];
        $field = 'text';
        $valueClass = CustomLocalizedFallbackValueStub::class;

        $builder = $this->createMock('Symfony\Component\Form\FormBuilderInterface');
        $builder->expects($this->at(0))
            ->method('add')
            ->with(
                LocalizedFallbackValueCollectionType::FIELD_VALUES,
                LocalizedPropertyType::class,
                [
                    'entry_type' => $type,
                    'entry_options' => $options,
                    'exclude_parent_localization' => false,
                    'use_tabs' => true
                ]
            )->willReturnSelf();
        $builder->expects($this->at(1))
            ->method('add')
            ->with(
                LocalizedFallbackValueCollectionType::FIELD_IDS,
                CollectionType::class,
                ['entry_type' => HiddenType::class]
            )->willReturnSelf();
        $builder->expects($this->once())
            ->method('addViewTransformer')
            ->with(new LocalizedFallbackValueCollectionTransformer($this->registry, $field, $valueClass))
            ->willReturnSelf();

        $this->type->buildForm(
            $builder,
            [
                'entry_type' => $type,
                'value_class' => $valueClass,
                'entry_options' => $options,
                'field' => $field,
                'exclude_parent_localization' => false,
                'use_tabs' => true
            ]
        );
    }

    public function testFinishView(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject|FormInterface $formMock */
        $formMock = $this->createMock('Symfony\Component\Form\FormInterface');

        $formView = new FormView();
        $formView->vars['block_prefixes'] = ['form', '_custom_block_prefix'];

        $this->type->finishView($formView, $formMock, ['use_tabs' => true]);

        $this->assertEquals(
            ['form', 'oro_locale_localized_fallback_value_collection_tabs', '_custom_block_prefix'],
            $formView->vars['block_prefixes']
        );
    }
}
