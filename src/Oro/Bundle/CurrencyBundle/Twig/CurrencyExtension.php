<?php

namespace Oro\Bundle\CurrencyBundle\Twig;

use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\CurrencyBundle\Provider\ViewTypeProviderInterface;
use Oro\Bundle\CurrencyBundle\Utils\CurrencyNameHelper;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\Intl\Currencies;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Provides a Twig function to retrieve currency display configuration:
 *   - oro_currency_view_type
 *
 * Provides Twig filter to format prices:
 *   - oro_format_price
 *   - oro_localized_currency_name
 */
class CurrencyExtension extends AbstractExtension implements ServiceSubscriberInterface
{
    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return NumberFormatter
     */
    protected function getNumberFormatter()
    {
        return $this->container->get('oro_locale.formatter.number');
    }

    /**
     * @return ViewTypeProviderInterface
     */
    protected function getViewTypeProvider()
    {
        return $this->container->get('oro_currency.provider.view_type');
    }

    /**
     * @return CurrencyNameHelper
     */
    protected function getCurrencyNameHelper()
    {
        return $this->container->get('oro_currency.helper.currency_name');
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('oro_currency_view_type', [$this, 'getViewType']),
            new TwigFunction(
                'oro_currency_symbol_collection',
                [$this, 'getSymbolCollection'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter(
                'oro_format_price',
                [$this, 'formatPrice'],
                ['is_safe' => ['html']]
            ),
            new TwigFilter('oro_localized_currency_name', [$this, 'getCurrencyName'])
        ];
    }

    /**
     * @return string
     */
    public function getViewType()
    {
        return $this->getViewTypeProvider()->getViewType();
    }

    /**
     * Formats currency number according to locale settings.
     *
     * Options format:
     * array(
     *     'attributes' => array(
     *          <attribute> => <value>,
     *          ...
     *      ),
     *     'textAttributes' => array(
     *          <attribute> => <value>,
     *          ...
     *      ),
     *     'symbols' => array(
     *          <symbol> => <value>,
     *          ...
     *      ),
     *     'locale' => <locale>
     * )
     *
     * @param Price $price
     * @param array $options
     * @return string
     */
    public function formatPrice(Price $price, array $options = [])
    {
        $value = $price->getValue();
        $currency = $price->getCurrency();

        $attributes = (array)$this->getOption($options, 'attributes', []);
        $textAttributes = (array)$this->getOption($options, 'textAttributes', []);
        $symbols = (array)$this->getOption($options, 'symbols', []);
        $locale = $this->getOption($options, 'locale');

        $formattedValue = $this->getNumberFormatter()
            ->formatCurrency($value, $currency, $attributes, $textAttributes, $symbols, $locale);

        return strip_tags($formattedValue);
    }

    /**
     * Returns symbols for active currencies
     *
     * @return array Collection of active currency codes and symbols
     */
    public function getSymbolCollection()
    {
        $currencySymbolCollection = $this->getCurrencyNameHelper()
            ->getCurrencyChoices(ViewTypeProviderInterface::VIEW_TYPE_SYMBOL);

        $currencySymbolCollection = array_map(
            function ($symbol) {
                return ['symbol' => $symbol];
            },
            array_flip($currencySymbolCollection)
        );

        return $currencySymbolCollection;
    }

    /**
     * @param string      $currency
     * @param string|null $displayLocale
     *
     * @return string|null
     */
    public function getCurrencyName($currency, $displayLocale = null)
    {
        return Currencies::getName($currency, $displayLocale);
    }

    /**
     * Gets option or default value if option not exist
     *
     * @param array $options
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getOption(array $options, $name, $default = null)
    {
        return isset($options[$name]) ? $options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_currency';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return [
            'oro_locale.formatter.number' => NumberFormatter::class,
            'oro_currency.provider.view_type' => ViewTypeProviderInterface::class,
            'oro_currency.helper.currency_name' => CurrencyNameHelper::class,
        ];
    }
}
