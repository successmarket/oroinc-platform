<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Form\Extension\JsValidation;

use Oro\Bundle\FormBundle\Form\Extension\JsValidation\RepeatedTypeExtension;
use Symfony\Component\Form\FormView;

class RepeatedTypeExtensionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RepeatedTypeExtension
     */
    protected $extension;

    protected function setUp(): void
    {
        $this->extension = new RepeatedTypeExtension();
    }

    /**
     * @dataProvider finishViewDataProvider
     */
    public function testFinishView(
        FormView $view,
        array $options,
        array $expectedVars,
        array $expectedChildrenVars
    ) {
        $form = $this->createMock('Symfony\Component\Form\Test\FormInterface');

        $this->extension->finishView($view, $form, $options);

        $this->assertEquals($expectedVars, $view->vars);
        $this->assertEquals(count($expectedChildrenVars), count($view->children));

        foreach ($expectedChildrenVars as $childName => $expectedVars) {
            $this->assertArrayHasKey($childName, $view->children);
            $this->assertEquals($expectedVars, $view->children[$childName]->vars);
        }
    }

    public function finishViewDataProvider()
    {
        return array(
            'default' => array(
                'formView' => $this->createView(
                    array(),
                    array(
                        'first' => $this->createView(),
                        'second' => $this->createView(),
                    )
                ),
                'options' => array(
                    'first_name' => 'first',
                    'second_name' => 'second',
                    'invalid_message' => 'Some invalid message',
                    'invalid_message_parameters' => array(1),
                ),
                'expectedVars' => array(
                    'value' => null,
                    'attr' => array(),
                ),
                'expectedChildrenVars' => array(
                    'first' => array(
                        'value' => null,
                        'attr' => array()
                    ),
                    'second' => array(
                        'value' => null,
                        'attr' => array(
                            'data-validation' => json_encode(
                                array(
                                    'Repeated' => array(
                                        'first_name' => 'first',
                                        'second_name' => 'second',
                                        'invalid_message' => 'Some invalid message',
                                        'invalid_message_parameters' => array(1),
                                    )
                                )
                            )
                        )
                    )
                )
            ),
            'copy_attr_to_first_children' => array(
                'formView' => $this->createView(
                    array(
                        'attr' => array(
                            'data-validation' => json_encode(array('NotBlank' => array()))
                        ),
                    ),
                    array(
                        'first' => $this->createView(),
                        'second' => $this->createView(),
                    )
                ),
                'options' => array(
                    'first_name' => 'first',
                    'second_name' => 'second',
                    'invalid_message' => 'Some invalid message',
                    'invalid_message_parameters' => array(1),
                ),
                'expectedVars' => array(
                    'value' => null,
                    'attr' => array(),
                ),
                'expectedChildrenVars' => array(
                    'first' => array(
                        'value' => null,
                        'attr' => array(
                            'data-validation' => json_encode(array('NotBlank' => array()))
                        )
                    ),
                    'second' => array(
                        'value' => null,
                        'attr' => array(
                            'data-validation' => json_encode(
                                array(
                                    'Repeated' => array(
                                        'first_name' => 'first',
                                        'second_name' => 'second',
                                        'invalid_message' => 'Some invalid message',
                                        'invalid_message_parameters' => array(1),
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @param array $vars
     * @param array $children
     * @param FormView $parent
     * @return FormView
     */
    protected function createView(array $vars = array(), array $children = array(), FormView $parent = null)
    {
        $result = new FormView();
        $result->vars = array_merge_recursive($result->vars, $vars);
        $result->children = $children;
        $result->parent = $parent;
        return $result;
    }
}
