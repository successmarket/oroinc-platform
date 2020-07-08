<?php

namespace Oro\Bundle\AttachmentBundle\Form\Type;

use Oro\Bundle\AttachmentBundle\Entity\FileItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form Type for FileItem entity
 */
class FileItemType extends AbstractType
{
    const TYPE = 'oro_attachment_file_item';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('sortOrder', NumberType::class, [
            'block_prefix' => $this->getBlockPrefix() . '_sortOrder',
        ])->add('file', $options['file_type'], [
            'block_prefix' => $this->getBlockPrefix() . '_file',
            'allowDelete' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => FileItem::class,
            'file_type' => FileType::class,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::TYPE;
    }
}
