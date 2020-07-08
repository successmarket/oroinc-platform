<?php

namespace Oro\Bundle\CurrencyBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataContext;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Sets the current currency to an entity.
 */
class SetCurrency implements ProcessorInterface
{
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;

    /** @var LocaleSettings */
    private $localeSettings;

    /** @var string */
    private $currencyFieldName;

    /**
     * @param PropertyAccessorInterface $propertyAccessor
     * @param LocaleSettings            $localeSettings
     * @param string                    $currencyFieldName
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        LocaleSettings $localeSettings,
        string $currencyFieldName = 'currency'
    ) {
        $this->propertyAccessor = $propertyAccessor;
        $this->localeSettings = $localeSettings;
        $this->currencyFieldName = $currencyFieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var CustomizeFormDataContext $context */

        $currencyFormField = $context->findFormField($this->currencyFieldName);
        if (null === $currencyFormField
            || !$currencyFormField->isSubmitted()
            || !$currencyFormField->getConfig()->getMapped()
        ) {
            $this->setCurrency($context->getData());
        }
    }

    /**
     * Returns a currency that should be set to a processing entity.
     *
     * @return string|null
     */
    private function getCurrency(): ?string
    {
        return $this->localeSettings->getCurrency();
    }

    /**
     * Sets a currency returned by getCurrency() method to the given entity.
     * The entity's currency property will not be changed if the getCurrency() method returns NULL
     * or a currency is already set to the entity.
     *
     * @param object $entity
     */
    private function setCurrency($entity): void
    {
        $entityCurrency = $this->propertyAccessor->getValue($entity, $this->currencyFieldName);
        if (null === $entityCurrency) {
            $currency = $this->getCurrency();
            if (null !== $currency) {
                $this->propertyAccessor->setValue($entity, $this->currencyFieldName, $currency);
            }
        }
    }
}
