<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type\Stub;

use Oro\Component\Testing\Unit\Form\Type\Stub\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalizedFallbackValueTypeStub extends AbstractType
{
    const NAME = 'stub_localized_fallback_value';

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('string', TextType::class)
            ->add('text', TextType::class)
            ->add('localization', EntityType::class);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => 'Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue']);
    }
}
