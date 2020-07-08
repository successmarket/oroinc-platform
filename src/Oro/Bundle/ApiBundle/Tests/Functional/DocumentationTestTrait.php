<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor;
use Nelmio\ApiDocBundle\Formatter\FormatterInterface;
use Oro\Bundle\ApiBundle\ApiDoc\Extractor\CachingApiDocExtractor;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\TestFrameworkBundle\Entity\TestFrameworkEntityInterface;
use Symfony\Component\Routing\Route;

/**
 * It is expected that a test is used this trait has the following:
 * * VIEW constant, e.g.: private const VIEW = 'rest_json_api';
 * * static method getContainer()
 */
trait DocumentationTestTrait
{
    /** @var int */
    private static $resourceErrorCount = 3;

    /**
     * @see \Oro\Bundle\ApiBundle\ApiDoc\ResourceDocProvider::TEMPLATES
     * @var array
     */
    private static $defaultDocumentation = [
        ApiAction::GET                 => 'Get an entity',
        ApiAction::GET_LIST            => 'Get a list of entities',
        ApiAction::DELETE              => 'Delete an entity',
        ApiAction::DELETE_LIST         => 'Delete a list of entities',
        ApiAction::CREATE              => 'Create an entity',
        ApiAction::UPDATE              => 'Update an entity',
        ApiAction::GET_SUBRESOURCE     => [
            'Get a related entity',
            'Get a list of related entities'
        ],
        ApiAction::DELETE_SUBRESOURCE  => [
            'Delete the specified related entity',
            'Delete the specified related entities'
        ],
        ApiAction::ADD_SUBRESOURCE     => [
            'Add the specified related entity',
            'Add the specified related entities'
        ],
        ApiAction::UPDATE_SUBRESOURCE  => [
            'Update the specified related entity',
            'Update the specified related entities'
        ],
        ApiAction::GET_RELATIONSHIP    => 'Get the relationship data',
        ApiAction::DELETE_RELATIONSHIP => 'Delete the specified members from the relationship',
        ApiAction::ADD_RELATIONSHIP    => 'Add the specified members to the relationship',
        ApiAction::UPDATE_RELATIONSHIP => [
            'Update the relationship',
            'Completely replace every member of the relationship'
        ]
    ];

    /**
     * @param string $entityClass
     * @param string $entityType
     *
     * @return bool
     */
    private function isSkippedEntity($entityClass, $entityType)
    {
        return
            is_a($entityClass, TestFrameworkEntityInterface::class, true)
            || 0 === strpos($entityType, 'testapi')
            || (// custom entities (entities from "Extend\Entity" namespace), except enums
                0 === strpos($entityClass, ExtendHelper::ENTITY_NAMESPACE)
                && (
                    0 !== strpos($entityClass, ExtendHelper::ENTITY_NAMESPACE . 'EV_')
                    || 0 === strpos($entityClass, ExtendHelper::ENTITY_NAMESPACE . 'EV_Test_')
                )
            );
    }

    /**
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return bool
     */
    private function isSkippedField($entityClass, $fieldName)
    {
        return false;
    }

    /**
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return bool
     */
    private function isTestField($entityClass, $fieldName)
    {
        if (0 === strpos($fieldName, ExtendConfigDumper::DEFAULT_PREFIX)) {
            return $this->isTestField(
                $entityClass,
                substr($fieldName, strlen(ExtendConfigDumper::DEFAULT_PREFIX))
            );
        }

        $configManager = $this->getEntityConfigManager();
        if (!$configManager->hasConfig($entityClass, $fieldName)) {
            return false;
        }

        $label = $configManager->getFieldConfig('entity', $entityClass, $fieldName)->get('label');

        return 0 === strpos($label, 'extend.entity.test');
    }

    /**
     * @return ApiDocExtractor
     */
    private function getExtractor()
    {
        return self::getContainer()->get('nelmio_api_doc.extractor.api_doc_extractor');
    }

    /**
     * @return FormatterInterface
     */
    private function getSimpleFormatter()
    {
        return self::getContainer()->get('nelmio_api_doc.formatter.simple_formatter');
    }

    /**
     * @return ConfigManager
     */
    private function getEntityConfigManager()
    {
        return self::getContainer()->get('oro_entity_config.config_manager');
    }

    private function warmUpDocumentationCache()
    {
        $apiDocExtractor = $this->getExtractor();
        if ($apiDocExtractor instanceof CachingApiDocExtractor) {
            $apiDocExtractor->warmUp(self::VIEW);
        }
    }

    private function checkDocumentation()
    {
        $missingDocs = [];
        $docs = $this->getExtractor()->all(self::VIEW);
        foreach ($docs as $doc) {
            /** @var ApiDoc $annotation */
            $annotation = $doc['annotation'];
            $definition = $annotation->toArray();
            $route = $annotation->getRoute();

            $entityType = $route->getDefault('entity');
            $action = $route->getDefault('_action');
            $association = $route->getDefault('association');
            if ($entityType && $action) {
                $entityClass = $this->getEntityClass($entityType);
                if ($entityClass && !$this->isSkippedEntity($entityClass, $entityType)) {
                    $resourceMissingDocs = $this->checkApiResource($definition, $entityClass, $action, $association);
                    if (!empty($resourceMissingDocs)) {
                        $resource = sprintf('%s %s', $definition['method'], $definition['uri']);
                        $missingDocs[$entityClass][$resource] = $resourceMissingDocs;
                    }
                }
            }
        }

        if (!empty($missingDocs)) {
            self::fail($this->buildFailMessage($missingDocs));
        }
    }

    /**
     * @param array  $definition
     * @param string $entityClass
     * @param string $action
     * @param string $association
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function checkApiResource(array $definition, $entityClass, $action, $association)
    {
        $missingDocs = [];
        if (empty($definition['description'])) {
            $missingDocs[] = 'Empty description';
        }
        if (empty($definition['documentation'])) {
            $missingDocs[] = 'Empty documentation';
        } elseif (isset(self::$defaultDocumentation[$action])
            && in_array($definition['documentation'], (array)self::$defaultDocumentation[$action], true)
            && (
                !$association
                || (
                    !$this->isSkippedField($entityClass, $association)
                    && !$this->isTestField($entityClass, $association)
                )
            )
        ) {
            $missingDocs[] = sprintf(
                'Missing documentation. Default value is used: "%s"',
                $definition['documentation']
            );
        } elseif ($this->hasDuplicates($definition['documentation'])) {
            $missingDocs[] = 'Duplicates in documentation. Full documentation:' . "\n" . $definition['documentation'];
        }
        if (!empty($definition['parameters'])) {
            $idFieldName = null;
            if (!empty($definition['requirements']) && count($definition['requirements']) === 1) {
                $idFieldName = key($definition['requirements']);
            }
            foreach ($definition['parameters'] as $name => $item) {
                if (!$this->isSkippedField($entityClass, $name) && !$this->isTestField($entityClass, $name)) {
                    if (empty($item['description'])) {
                        $missingDocs[] = sprintf('Input Field: %s. Empty description.', $name);
                    } elseif (ApiAction::UPDATE === $action && $idFieldName && $name === $idFieldName) {
                        $description = $item['description'];
                        if (false === strpos($description, 'The required field.')) {
                            $missingDocs[] = sprintf(
                                'Input Field: %s. No "The required field." note. (Description: "%s")',
                                $name,
                                $item['description']
                            );
                        } else {
                            $description = preg_replace('#\<\/?\w+\>#', '', $description);
                            $description = trim(substr($description, 0, strpos($description, 'The required field.')));
                            if (!$description) {
                                $missingDocs[] = sprintf(
                                    'Input Field: %s. Empty description before "The required field." note.'
                                    . ' (Description: "%s")',
                                    $name,
                                    $item['description']
                                );
                            }
                        }
                    }
                }
            }
        }
        if (!empty($definition['filters'])) {
            foreach ($definition['filters'] as $name => $item) {
                if (empty($item['description'])) {
                    $missingDocs[] = sprintf('Filter: %s. Empty description.', $name);
                }
            }
        }
        if (!$association && !empty($definition['response'])) {
            foreach ($definition['response'] as $name => $item) {
                if (empty($item['description'])
                    && !$this->isSkippedField($entityClass, $name)
                    && !$this->isTestField($entityClass, $name)
                ) {
                    $missingDocs[] = sprintf('Output Field: %s. Empty description.', $name);
                }
            }
        }

        return $missingDocs;
    }

    /**
     * @param string $documentation
     *
     * @return bool
     */
    private function hasDuplicates($documentation)
    {
        $delimiter = strpos($documentation, '.');
        if (false === $delimiter) {
            return false;
        }

        $firstSentence = substr($documentation, 0, $delimiter + 1);

        return
            str_word_count($firstSentence) >= 5
            && false !== strpos($documentation, $firstSentence, $delimiter);
    }

    /**
     * @param array $missingDocs
     *
     * @return string
     */
    private function buildFailMessage(array $missingDocs)
    {
        $message = sprintf(
            'Missing documentation for %s entit%s.' . PHP_EOL . PHP_EOL,
            count($missingDocs),
            count($missingDocs) > 1 ? 'ies' : 'y'
        );
        foreach ($missingDocs as $entityClass => $resources) {
            $message .= sprintf('%s' . PHP_EOL, $entityClass);
            foreach ($resources as $resource => $errors) {
                $message .= sprintf('    %s' . PHP_EOL, $resource);
                $i = 0;
                $errorCount = count($errors);
                foreach ($errors as $error) {
                    $message .= sprintf('        %s' . PHP_EOL, $error);
                    $i++;
                    if (self::$resourceErrorCount === $i && $errorCount > self::$resourceErrorCount + 2) {
                        $message .= sprintf('        and others %d errors ...' . PHP_EOL, $errorCount - $i);
                        break;
                    }
                }
            }
        }

        return $message;
    }

    /**
     * @param string $entityType
     * @param string $action
     *
     * @return array
     */
    private function getEntityDocsForAction($entityType, $action)
    {
        return $this->filterDocs(
            $this->getExtractor()->all(self::VIEW),
            function (Route $route) use ($entityType, $action) {
                return
                    $route->getDefault('entity') === $entityType
                    && $route->getDefault('_action') === $action;
            }
        );
    }

    /**
     * @param string $entityType
     * @param string $subresource
     * @param string $action
     *
     * @return array
     */
    private function getSubresourceEntityDocsForAction($entityType, $subresource, $action)
    {
        return $this->filterDocs(
            $this->getExtractor()->all(self::VIEW),
            function (Route $route) use ($entityType, $action, $subresource) {
                return
                    $route->getDefault('entity') === $entityType
                    && $route->getDefault('_action') === $action
                    && $route->getDefault('association') === $subresource;
            }
        );
    }

    /**
     * @param array    $docs
     * @param callable $filter function (Route $route) : bool
     *
     * @return array
     */
    private function filterDocs(array $docs, $filter)
    {
        $result = [];
        foreach ($docs as $doc) {
            /** @var ApiDoc $annotation */
            $annotation = $doc['annotation'];
            if ($filter($annotation->getRoute())) {
                $result[] = $doc;
            }
        }

        return $result;
    }

    /**
     * @param string $entityType
     */
    private function checkOptionsDocumentationForEntity($entityType)
    {
        $docs = $this->getEntityDocsForAction($entityType, ApiAction::OPTIONS);
        $data = $this->getSimpleFormatter()->format($docs);
        foreach ($data as $resource => $resourceData) {
            $resourceData = reset($resourceData);
            $this->checkOptionsDocumentationForResource($resource, $resourceData);
        }
    }

    /**
     * @param string $resource
     * @param string $resourceData
     */
    private function checkOptionsDocumentationForResource($resource, array $resourceData)
    {
        self::assertArrayContains(
            [
                'description'   => 'Get options',
                'documentation' => 'Get communication options for a resource'
            ],
            $resourceData,
            $resource
        );
        self::assertTrue(
            empty($resourceData['parameters']),
            sprintf('The "parameters" section for %s should be empty', $resource)
        );
        self::assertTrue(
            empty($resourceData['filters']),
            sprintf('The "filters" section for %s should be empty', $resource)
        );
        self::assertTrue(
            empty($resourceData['response']),
            sprintf('The "response" section for %s should be empty', $resource)
        );
    }
}
