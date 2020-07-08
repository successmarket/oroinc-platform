<?php

namespace Oro\Bundle\EntityBundle\Form\Type;

use Oro\Bundle\EntityBundle\Form\Handler\EntitySelectHandler;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\FormBundle\Autocomplete\ConverterInterface;
use Oro\Bundle\FormBundle\Form\Type\OroJquerySelect2HiddenType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form type to select an entity from a list.
 */
class EntitySelectType extends AbstractType
{
    const NAME = 'oro_entity_select';

    /** @var ConfigManager */
    protected $cm;

    /**
     * @param ConfigManager $cm
     */
    public function __construct(ConfigManager $cm)
    {
        $this->cm = $cm;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $vars = ['configs' => $options['configs']];
        if ($form->getData()) {
            $parentData = $form->getParent()->getData();
            $fieldConfig = $this->cm->getProvider('extend')->getConfig(get_class($parentData), $form->getName());
            if ($form->getData()) {
                /** @var ConverterInterface|EntitySelectHandler $converter */
                $converter = $options['converter'];

                if ($converter instanceof EntitySelectHandler) {
                    $converter->initForEntity($fieldConfig->getId()->getClassName(), $fieldConfig->get('target_field'));
                }

                if (isset($options['configs']['multiple']) && $options['configs']['multiple']) {
                    $result    = [];
                    foreach ($form->getData() as $item) {
                        $result[] = $converter->convertItem($item);
                    }
                } else {
                    $result = $converter->convertItem($form->getData());
                }

                $vars['attr'] = ['data-selected-data' => json_encode($result)];
            }
        }

        $view->vars = array_replace_recursive($view->vars, $vars);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'placeholder'        => 'oro.form.choose_value',
                'allowClear'         => true,
                'autocomplete_alias' => 'entity_select',
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return OroJquerySelect2HiddenType::class;
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
        return self::NAME;
    }
}
