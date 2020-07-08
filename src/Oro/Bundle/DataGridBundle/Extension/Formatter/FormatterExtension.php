<?php

namespace Oro\Bundle\DataGridBundle\Extension\Formatter;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Datagrid\Common\ResultsObject;
use Oro\Bundle\DataGridBundle\Exception\RuntimeException;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyConfiguration;
use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyInterface;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides a way to format a datagrid column value depends on its data-type.
 */
class FormatterExtension extends AbstractExtension
{
    /** @var string[] */
    private $propertyTypes;

    /** @var ContainerInterface */
    private $propertyContainer = [];

    /** @var TranslatorInterface */
    private $translator;

    /**
     * @param string[]            $propertyTypes
     * @param ContainerInterface  $propertyContainer
     * @param TranslatorInterface $translator
     */
    public function __construct(
        array $propertyTypes,
        ContainerInterface $propertyContainer,
        TranslatorInterface $translator
    ) {
        $this->propertyTypes = $propertyTypes;
        $this->propertyContainer = $propertyContainer;
        $this->translator = $translator;
    }

    /**
     * {@inheritDoc}
     */
    public function isApplicable(DatagridConfiguration $config)
    {
        if (!parent::isApplicable($config)) {
            return false;
        }

        $columns    = $config->offsetGetOr(Configuration::COLUMNS_KEY, []);
        $properties = $config->offsetGetOr(Configuration::PROPERTIES_KEY, []);
        $applicable = $columns || $properties;
        $this->processConfigs($config);

        return $applicable;
    }

    /**
     * Validate configs nad fill default values
     *
     * @param DatagridConfiguration $config
     */
    public function processConfigs(DatagridConfiguration $config)
    {
        $columns    = $config->offsetGetOr(Configuration::COLUMNS_KEY, []);
        $properties = $config->offsetGetOr(Configuration::PROPERTIES_KEY, []);

        // validate extension configuration and normalize by setting default values
        $columnsNormalized = $this->validateConfigurationByType($columns, Configuration::COLUMNS_KEY);
        $propertiesNormalized = $this->validateConfigurationByType($properties, Configuration::PROPERTIES_KEY);

        // replace config values by normalized, extra keys passed directly
        $config->offsetSet(Configuration::COLUMNS_KEY, array_replace_recursive($columns, $columnsNormalized))
            ->offsetSet(Configuration::PROPERTIES_KEY, array_replace_recursive($properties, $propertiesNormalized));
    }

    /**
     * {@inheritDoc}
     */
    public function visitResult(DatagridConfiguration $config, ResultsObject $result)
    {
        $rows       = $result->getData();
        $columns    = $config->offsetGetOr(Configuration::COLUMNS_KEY, []);
        $properties = $config->offsetGetOr(Configuration::PROPERTIES_KEY, []);
        $toProcess  = array_merge($columns, $properties);

        foreach ($rows as $key => $row) {
            $currentRow = [];
            foreach ($toProcess as $name => $propertyConfig) {
                $property = $this->getPropertyObject(PropertyConfiguration::createNamed($name, $propertyConfig));
                $currentRow[$name] = $property->getValue($row);
            }
            $rows[$key] = $currentRow;
        }

        $result->setData($rows);
    }

    /**
     * {@inheritDoc}
     */
    public function visitMetadata(DatagridConfiguration $config, MetadataObject $data)
    {
        // get only columns here because columns will be represented on frontend
        $columns = $config->offsetGetOr(Configuration::COLUMNS_KEY, []);

        $propertiesMetadata = [];
        foreach ($columns as $name => $fieldConfig) {
            $fieldConfig = PropertyConfiguration::createNamed($name, $fieldConfig);
            $metadata = $this->getPropertyObject($fieldConfig)->getMetadata();

            // translate label on backend
            $metadata['label'] = $metadata[PropertyInterface::TRANSLATABLE_KEY]
                ? $this->translator->trans($metadata['label'])
                : $metadata['label'];
            $propertiesMetadata[] = $metadata;
        }

        $data->offsetAddToArray('columns', $propertiesMetadata);
    }

    /**
     * Returns prepared property object
     *
     * @param PropertyConfiguration $config
     *
     * @return PropertyInterface
     */
    private function getPropertyObject(PropertyConfiguration $config): PropertyInterface
    {
        $type = $config->offsetGet(Configuration::TYPE_KEY);
        if (!$this->propertyContainer->has($type)) {
            throw new RuntimeException(sprintf('The "%s" formatter not found.', $type));
        }

        /** @var PropertyInterface $property */
        $property = $this->propertyContainer->get($type);
        $property->init($config);

        return $property;
    }

    /**
     * Validates specified type configuration
     *
     * @param array  $config
     * @param string $type
     *
     * @return array
     */
    private function validateConfigurationByType($config, $type)
    {
        return $this->validateConfiguration(
            new Configuration($this->propertyTypes, $type),
            [$type => $config]
        );
    }
}
