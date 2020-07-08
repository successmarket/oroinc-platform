<?php

namespace Oro\Bundle\ApiBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * The data transformer that returns a transformed value as is.
 * This data transformer is used to prevent replacing NULL with empty string by Symfony Forms.
 * @see \Symfony\Component\Form\Form::normToView
 * @see \Oro\Bundle\ApiBundle\Form\ApiFormBuilder::getViewTransformers
 */
final class NullTransformer implements DataTransformerInterface
{
    /** @var NullTransformer|null */
    private static $instance;

    /**
     * A private constructor to prevent create an instance of this class explicitly
     */
    private function __construct()
    {
    }

    /**
     * @return NullTransformer
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        return $value;
    }
}
