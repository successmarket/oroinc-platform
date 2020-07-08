<?php

namespace Oro\Bundle\ApiBundle\Processor;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Component\ChainProcessor\ParameterBagInterface;

/**
 * The base execution context for processors for "customize_loaded_data" and "customize_form_data" actions.
 */
abstract class CustomizeDataContext extends ApiContext implements SharedDataAwareContextInterface
{
    /** FQCN of a root entity */
    const ROOT_CLASS_NAME = 'rootClass';

    /** a path from a root entity to a customizing entity */
    const PROPERTY_PATH = 'propertyPath';

    /** FQCN of a customizing entity */
    const CLASS_NAME = 'class';

    /** @var EntityDefinitionConfig|null */
    private $rootConfig;

    /** @var EntityDefinitionConfig|null */
    private $config;

    /** @var ParameterBagInterface|null */
    private $sharedData;

    /**
     * Gets FQCN of a root entity.
     *
     * @return string|null
     */
    public function getRootClassName()
    {
        return $this->get(self::ROOT_CLASS_NAME);
    }

    /**
     * Sets FQCN of a root entity.
     *
     * @param string $className
     */
    public function setRootClassName($className)
    {
        $this->set(self::ROOT_CLASS_NAME, $className);
    }

    /**
     * Gets a path from a root entity to a customizing entity.
     *
     * @return string|null
     */
    public function getPropertyPath()
    {
        return $this->get(self::PROPERTY_PATH);
    }

    /**
     * Sets a path from a root entity to a customizing entity.
     *
     * @param string $propertyPath
     */
    public function setPropertyPath($propertyPath)
    {
        $this->set(self::PROPERTY_PATH, $propertyPath);
    }

    /**
     * Gets FQCN of a customizing entity.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->get(self::CLASS_NAME);
    }

    /**
     * Sets FQCN of a customizing entity.
     *
     * @param string $className
     */
    public function setClassName($className)
    {
        $this->set(self::CLASS_NAME, $className);
    }

    /**
     * Gets a configuration of a root entity.
     *
     * @return EntityDefinitionConfig|null
     */
    public function getRootConfig()
    {
        return $this->rootConfig;
    }

    /**
     * Sets a configuration of a root entity.
     *
     * @param EntityDefinitionConfig|null $config
     */
    public function setRootConfig(EntityDefinitionConfig $config = null)
    {
        $this->rootConfig = $config;
    }

    /**
     * Gets a configuration of a customizing entity.
     *
     * @return EntityDefinitionConfig|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets a configuration of a customizing entity.
     *
     * @param EntityDefinitionConfig|null $config
     */
    public function setConfig(EntityDefinitionConfig $config = null)
    {
        $this->config = $config;
    }

    /**
     * Gets an object that is used to share data between a primary action
     * and actions that are executed as part of this action.
     * Also, this object can be used to share data between different kind of child actions.
     *
     * @return ParameterBagInterface
     */
    public function getSharedData(): ParameterBagInterface
    {
        return $this->sharedData;
    }

    /**
     * Sets an object that is used to share data between a primary action
     * and actions that are executed as part of this action.
     * Also, this object can be used to share data between different kind of child actions.
     *
     * @param ParameterBagInterface $sharedData
     */
    public function setSharedData(ParameterBagInterface $sharedData): void
    {
        $this->sharedData = $sharedData;
    }

    /**
     * Gets a context for response data normalization.
     *
     * @return array
     */
    public function getNormalizationContext(): array
    {
        return [
            self::ACTION       => $this->getAction(),
            self::VERSION      => $this->getVersion(),
            self::REQUEST_TYPE => $this->getRequestType(),
            'sharedData'       => $this->getSharedData()
        ];
    }
}
