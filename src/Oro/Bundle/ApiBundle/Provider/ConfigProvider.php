<?php

namespace Oro\Bundle\ApiBundle\Provider;

use Oro\Bundle\ApiBundle\Config\Config;
use Oro\Bundle\ApiBundle\Config\Extra\ConfigExtraInterface;
use Oro\Bundle\ApiBundle\Config\Extra\ConfigExtraSectionInterface;
use Oro\Bundle\ApiBundle\Config\Extra\EntityDefinitionConfigExtra;
use Oro\Bundle\ApiBundle\Config\Extra\FilterIdentifierFieldsConfigExtra;
use Oro\Bundle\ApiBundle\Exception\RuntimeException;
use Oro\Bundle\ApiBundle\Processor\GetConfig\ConfigContext;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Component\ChainProcessor\ActionProcessorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides the configuration for a specific API resource.
 */
class ConfigProvider implements ResetInterface
{
    private const KEY_DELIMITER = '|';

    /** @var ActionProcessorInterface */
    private $processor;

    /** @var array */
    private $cache = [];

    /** @var array */
    private $processing = [];

    /** @var bool */
    private $fullConfigsCacheDisabled = false;

    /**
     * @param ActionProcessorInterface $processor
     */
    public function __construct(ActionProcessorInterface $processor)
    {
        $this->processor = $processor;
    }

    /**
     * Gets a config for the given version of an entity.
     *
     * @param string                 $className   The FQCN of an entity
     * @param string                 $version     The version of a config
     * @param RequestType            $requestType The request type, for example "rest", "soap", etc.
     * @param ConfigExtraInterface[] $extras      Requests for configuration data
     *
     * @return Config
     */
    public function getConfig(
        string $className,
        string $version,
        RequestType $requestType,
        array $extras = []
    ): Config {
        if (!$className) {
            throw new \InvalidArgumentException('$className must not be empty.');
        }

        $identifierFieldsOnly = false;
        $hasDefinitionExtra = false;
        $cacheKey = (string)$requestType . self::KEY_DELIMITER . $version . self::KEY_DELIMITER . $className;
        foreach ($extras as $extra) {
            $part = $extra->getCacheKeyPart();
            if ($part) {
                $cacheKey .= self::KEY_DELIMITER . $part;
            }
            if ($extra instanceof FilterIdentifierFieldsConfigExtra) {
                $identifierFieldsOnly = true;
            } elseif ($extra instanceof EntityDefinitionConfigExtra) {
                $hasDefinitionExtra = true;
            }
        }
        if (!$hasDefinitionExtra) {
            throw new \LogicException(sprintf(
                'The "%s" config extra must be specified. Class Name: %s.',
                EntityDefinitionConfigExtra::class,
                $className
            ));
        }

        if (!$identifierFieldsOnly && $this->fullConfigsCacheDisabled) {
            return $this->loadConfig($className, $version, $requestType, $extras, $identifierFieldsOnly, $cacheKey);
        }

        if (\array_key_exists($cacheKey, $this->cache)) {
            return clone $this->cache[$cacheKey];
        }

        $config = $this->loadConfig($className, $version, $requestType, $extras, $identifierFieldsOnly, $cacheKey);
        $this->cache[$cacheKey] = $config;

        return clone $config;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->cache = [];
    }

    public function enableFullConfigsCache(): void
    {
        $this->fullConfigsCacheDisabled = false;
    }

    public function disableFullConfigsCache(): void
    {
        $this->fullConfigsCacheDisabled = true;
    }

    /**
     * @param string      $className
     * @param string      $version
     * @param RequestType $requestType
     * @param array       $extras
     * @param bool        $identifierFieldsOnly
     * @param string      $cacheKey
     *
     * @return Config
     */
    private function loadConfig(
        string $className,
        string $version,
        RequestType $requestType,
        array $extras,
        bool $identifierFieldsOnly,
        string $cacheKey
    ): Config {
        if (isset($this->processing[$cacheKey])) {
            throw new RuntimeException(sprintf(
                'Cannot build the configuration of "%s" because this causes the circular dependency.',
                $className
            ));
        }

        /** @var ConfigContext $context */
        $context = $this->processor->createContext();
        $context->setClassName($className);
        $context->setVersion($version);
        $context->getRequestType()->set($requestType);
        $context->set(FilterIdentifierFieldsConfigExtra::NAME, $identifierFieldsOnly);
        if (!empty($extras)) {
            $context->setExtras($extras);
        }

        $this->processing[$cacheKey] = true;
        try {
            $this->processor->process($context);
        } finally {
            unset($this->processing[$cacheKey]);
        }

        $config = $this->buildResult($context);

        if ($identifierFieldsOnly || !$this->fullConfigsCacheDisabled) {
            $definition = $config->getDefinition();
            if (null !== $definition) {
                $definition->setKey($this->buildConfigKey($className, $extras));
            }
        }

        return $config;
    }

    /**
     * @param string                 $className
     * @param ConfigExtraInterface[] $extras
     *
     * @return string
     */
    private function buildConfigKey(string $className, array $extras): string
    {
        $configKey = $className;
        foreach ($extras as $extra) {
            if ($extra instanceof ConfigExtraSectionInterface) {
                continue;
            }
            $part = $extra->getCacheKeyPart();
            if ($part) {
                $configKey .= self::KEY_DELIMITER . $part;
            }
        }

        return $configKey;
    }

    /**
     * @param ConfigContext $context
     *
     * @return Config
     */
    private function buildResult(ConfigContext $context): Config
    {
        $config = new Config();
        if ($context->hasResult()) {
            $config->setDefinition($context->getResult());
        }
        $extras = $context->getExtras();
        foreach ($extras as $extra) {
            if ($extra instanceof ConfigExtraSectionInterface) {
                $sectionName = $extra->getName();
                if ($context->has($sectionName)) {
                    $config->set($sectionName, $context->get($sectionName));
                }
            }
        }

        return $config;
    }
}
