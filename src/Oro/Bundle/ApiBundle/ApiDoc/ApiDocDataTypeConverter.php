<?php

namespace Oro\Bundle\ApiBundle\ApiDoc;

/**
 * Helps to convert a data-type to a data-type that should be returned in API documentation.
 */
class ApiDocDataTypeConverter
{
    /** @var array [data type => data type in documentation, ...] */
    private $defaultMapping;

    /** @var array [view name => [data type => data type in documentation, ...], ...] */
    private $viewMappings;

    /**
     * @param array $defaultMapping [data type => data type in documentation, ...]
     * @param array $viewMappings   [view name => [data type => data type in documentation, ...], ...]
     */
    public function __construct(array $defaultMapping, array $viewMappings)
    {
        $this->defaultMapping = $defaultMapping;
        $this->viewMappings = $viewMappings;
    }

    /**
     * Converts a data-type to a data-type that should be returned in API documentation.
     *
     * @param string $dataType
     * @param string $view
     *
     * @return string
     */
    public function convertDataType(string $dataType, string $view): string
    {
        return $this->viewMappings[$view][$dataType] ?? $this->defaultMapping[$dataType] ?? $dataType;
    }
}
