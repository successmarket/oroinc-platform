<?php

namespace Oro\Bundle\ApiBundle\Request\JsonApi;

use Oro\Bundle\ApiBundle\Config\Extra\ExpandRelatedEntitiesConfigExtra;
use Oro\Bundle\ApiBundle\Config\Extra\FilterFieldsConfigExtra;
use Oro\Bundle\ApiBundle\Exception\NotSupportedConfigOperationException;
use Oro\Bundle\ApiBundle\Filter\FilterNamesRegistry;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Model\ErrorSource;
use Oro\Bundle\ApiBundle\Request\AbstractErrorCompleter;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\ExceptionTextExtractorInterface;
use Oro\Bundle\ApiBundle\Request\JsonApi\JsonApiDocumentBuilder as JsonApiDoc;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\ExceptionUtil;
use Oro\Bundle\ApiBundle\Util\ValueNormalizerUtil;

/**
 * The error completer for REST API response conforms JSON:API specification.
 */
class ErrorCompleter extends AbstractErrorCompleter
{
    private const POINTER_DELIMITER = '/';

    /** @var ValueNormalizer */
    private $valueNormalizer;

    /** @var FilterNamesRegistry */
    private $filterNamesRegistry;

    /**
     * @param ExceptionTextExtractorInterface $exceptionTextExtractor
     * @param ValueNormalizer                 $valueNormalizer
     * @param FilterNamesRegistry             $filterNamesRegistry
     */
    public function __construct(
        ExceptionTextExtractorInterface $exceptionTextExtractor,
        ValueNormalizer $valueNormalizer,
        FilterNamesRegistry $filterNamesRegistry
    ) {
        parent::__construct($exceptionTextExtractor);
        $this->valueNormalizer = $valueNormalizer;
        $this->filterNamesRegistry = $filterNamesRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function complete(Error $error, RequestType $requestType, EntityMetadata $metadata = null): void
    {
        $this->completeStatusCode($error);
        $this->completeCode($error);
        $this->completeTitle($error);
        $this->completeDetail($error);
        $this->completeSource($error, $requestType, $metadata);
    }


    /**
     * {@inheritdoc}
     */
    public function fixIncludedEntityPath(
        string $entityPath,
        Error $error,
        RequestType $requestType,
        EntityMetadata $metadata = null
    ): void {
        $this->completeSource($error, $requestType, $metadata);
        $errorSource = $error->getSource();
        if (null === $errorSource) {
            $error->setSource(ErrorSource::createByPointer($entityPath));
        } else {
            $pointer = $errorSource->getPointer();
            if ($pointer && 0 === \strpos($pointer, self::POINTER_DELIMITER . JsonApiDoc::DATA)) {
                $errorSource->setPointer($entityPath . \substr($pointer, \strlen(JsonApiDoc::DATA) + 1));
            } else {
                $propertyPath = $errorSource->getPropertyPath();
                if ($propertyPath) {
                    $propertyPath = \str_replace(self::POINTER_DELIMITER, ConfigUtil::PATH_DELIMITER, $entityPath)
                        . ConfigUtil::PATH_DELIMITER
                        . $propertyPath;
                    if (0 === \strpos($propertyPath, ConfigUtil::PATH_DELIMITER)) {
                        $propertyPath = \substr($propertyPath, 1);
                    }
                    $errorSource->setPropertyPath($propertyPath);
                }
            }
        }
    }

    /**
     * @param Error               $error
     * @param RequestType         $requestType
     * @param EntityMetadata|null $metadata
     */
    private function completeSource(Error $error, RequestType $requestType, EntityMetadata $metadata = null): void
    {
        $source = $error->getSource();
        if (null === $source && $this->isConfigFilterConstraintViolation($error)) {
            $error->setSource(
                ErrorSource::createByParameter($this->getConfigFilterConstraintParameter($error, $requestType))
            );
        } elseif (null !== $source && !$source->getPointer() && $source->getPropertyPath()) {
            $propertyPath = $source->getPropertyPath();
            if (null === $metadata) {
                $error->setDetail($this->appendSourceToMessage($error->getDetail(), $propertyPath));
                $error->setSource();
            } else {
                list($normalizedPropertyPath, $path, $pointerPrefix) = $this->normalizePropertyPath($propertyPath);
                $pointer = $this->getPointer($metadata, $normalizedPropertyPath, $path);
                if (empty($pointer)) {
                    $error->setDetail($this->appendSourceToMessage($error->getDetail(), $propertyPath));
                    $error->setSource();
                } else {
                    $dataSection = $metadata->hasIdentifierFields()
                        ? JsonApiDoc::DATA
                        : JsonApiDoc::META;
                    $source->setPointer(
                        self::POINTER_DELIMITER
                        . $dataSection
                        . self::POINTER_DELIMITER
                        . \implode(self::POINTER_DELIMITER, \array_merge($pointerPrefix, $pointer))
                    );
                    $source->setPropertyPath(null);
                }
            }
        }
    }

    /**
     * @param string $propertyPath
     *
     * @return array [normalized property path, path, pointer prefix]
     */
    private function normalizePropertyPath(string $propertyPath): array
    {
        $pointerPrefix = [];
        $normalizedPropertyPath = $propertyPath;
        $path = \explode(ConfigUtil::PATH_DELIMITER, $propertyPath);
        if (\count($path) > 1 && \is_numeric($path[0])) {
            $normalizedPropertyPath = \substr($propertyPath, \strlen($path[0]) + 1);
            $pointerPrefix[] = \array_shift($path);
        }

        return [$normalizedPropertyPath, $path, $pointerPrefix];
    }

    /**
     * @param EntityMetadata $metadata
     * @param string         $normalizedPropertyPath
     * @param string[]       $path
     *
     * @return string[]
     */
    private function getPointer(EntityMetadata $metadata, string $normalizedPropertyPath, array $path): array
    {
        $pointer = [];
        if (\in_array($normalizedPropertyPath, $metadata->getIdentifierFieldNames(), true)) {
            $pointer[] = JsonApiDoc::ID;
        } elseif ($metadata->hasField($normalizedPropertyPath)) {
            if ($metadata->hasIdentifierFields()) {
                $pointer = [JsonApiDoc::ATTRIBUTES, $normalizedPropertyPath];
            } else {
                $pointer = [$normalizedPropertyPath];
            }
        } elseif ($metadata->hasAssociation($path[0])) {
            if ($metadata->hasIdentifierFields()) {
                $pointer = $this->getAssociationPointer($path, $metadata->getAssociation($path[0]));
            } else {
                $pointer = [$normalizedPropertyPath];
            }
        } elseif ($metadata->hasMetaProperty($normalizedPropertyPath)) {
            if ($metadata->hasIdentifierFields()) {
                $pointer = [JsonApiDoc::META, $normalizedPropertyPath];
            } else {
                $pointer = [$normalizedPropertyPath];
            }
        }

        return $pointer;
    }

    /**
     * @param string[]            $path
     * @param AssociationMetadata $association
     *
     * @return string[]
     */
    private function getAssociationPointer(array $path, AssociationMetadata $association): array
    {
        $pointer = DataType::isAssociationAsField($association->getDataType())
            ? [JsonApiDoc::ATTRIBUTES, $path[0]]
            : [JsonApiDoc::RELATIONSHIPS, $path[0], JsonApiDoc::DATA];
        if (\count($path) > 1) {
            $pointer[] = $path[1];
            if (!$association->isCollapsed() && DataType::isAssociationAsField($association->getDataType())) {
                $pointer = \array_merge($pointer, \array_slice($path, 2));
            }
        }

        return $pointer;
    }

    /**
     * @param string|null $message
     * @param string      $source
     *
     * @return string
     */
    private function appendSourceToMessage(?string $message, string $source): string
    {
        if (!$message) {
            return \sprintf('Source: %s.', $source);
        }

        if (!$this->endsWith($message, '.')) {
            $message .= '.';
        }

        return \sprintf('%s Source: %s.', $message, $source);
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    private function endsWith(string $haystack, string $needle): bool
    {
        return \substr($haystack, -\strlen($needle)) === $needle;
    }

    /**
     * @param Error       $error
     * @param RequestType $requestType
     *
     * @return string
     */
    private function getConfigFilterConstraintParameter(Error $error, RequestType $requestType): string
    {
        $filterNames = $this->filterNamesRegistry->getFilterNames($requestType);
        /** @var NotSupportedConfigOperationException $e */
        $e = ExceptionUtil::getProcessorUnderlyingException($error->getInnerException());
        if (ExpandRelatedEntitiesConfigExtra::NAME === $e->getOperation()) {
            return $filterNames->getIncludeFilterName();
        }
        if (FilterFieldsConfigExtra::NAME === $e->getOperation()) {
            return \sprintf(
                $filterNames->getFieldsFilterTemplate(),
                $this->getEntityType($e->getClassName(), $requestType)
            );
        }

        throw new \LogicException(\sprintf(
            'Unexpected type of NotSupportedConfigOperationException: %s.',
            $e->getOperation()
        ));
    }

    /**
     * @param string      $entityClass
     * @param RequestType $requestType
     *
     * @return string|null
     */
    private function getEntityType(string $entityClass, RequestType $requestType): ?string
    {
        return ValueNormalizerUtil::convertToEntityType(
            $this->valueNormalizer,
            $entityClass,
            $requestType,
            false
        );
    }
}
