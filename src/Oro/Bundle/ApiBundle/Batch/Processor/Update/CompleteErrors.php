<?php

namespace Oro\Bundle\ApiBundle\Batch\Processor\Update;

use Oro\Bundle\ApiBundle\Batch\Handler\BatchUpdateItem;
use Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem\BatchUpdateItemContext;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Request\ErrorCompleterRegistry;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Checks if there are any errors in the context or contexts of batch items,
 * and if so, completes missing properties of all Error objects.
 * E.g. if an error is created due to an exception occurs,
 * such error does not have "statusCode", "title", "detail" and other properties,
 * and these properties are extracted from the Exception object.
 * Also, removes duplicated errors if any.
 */
class CompleteErrors implements ProcessorInterface
{
    /** @var ErrorCompleterRegistry */
    private $errorCompleterRegistry;

    /**
     * @param ErrorCompleterRegistry $errorCompleterRegistry
     */
    public function __construct(ErrorCompleterRegistry $errorCompleterRegistry)
    {
        $this->errorCompleterRegistry = $errorCompleterRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var BatchUpdateContext $context */

        $requestType = $context->getRequestType();
        $errorCompleter = $this->errorCompleterRegistry->getErrorCompleter($requestType);
        $errors = $context->getErrors();
        foreach ($errors as $error) {
            $errorCompleter->complete($error, $requestType);
        }
        $items = $context->getBatchItems();
        if ($items) {
            foreach ($items as $item) {
                $itemContext = $item->getContext();
                $errors = $itemContext->getErrors();
                if (!empty($errors)) {
                    $metadata = $this->getItemMetadata($item);
                    foreach ($errors as $error) {
                        $errorCompleter->complete($error, $requestType, $metadata);
                    }
                    if (\count($errors) > 1) {
                        $this->removeDuplicates($errors, $itemContext);
                    }
                }
            }
        }
    }

    /**
     * @param Error[]                $errors
     * @param BatchUpdateItemContext $context
     */
    private function removeDuplicates(array $errors, BatchUpdateItemContext $context): void
    {
        $context->resetErrors();
        $map = [];
        foreach ($errors as $error) {
            $key = $this->getErrorHash($error);
            if (!isset($map[$key])) {
                $map[$key] = true;
                $context->addError($error);
            }
        }
    }

    /**
     * @param Error $error
     *
     * @return string
     */
    private function getErrorHash(Error $error): string
    {
        $result = serialize([
            $error->getStatusCode(),
            $error->getCode(),
            $error->getTitle(),
            $error->getDetail()
        ]);
        $source = $error->getSource();
        if (null !== $source) {
            $result .= serialize([
                $source->getPropertyPath(),
                $source->getPointer(),
                $source->getParameter()
            ]);
        }

        return $result;
    }

    /**
     * @param BatchUpdateItem $item
     *
     * @return EntityMetadata|null
     */
    private function getItemMetadata(BatchUpdateItem $item): ?EntityMetadata
    {
        $targetContext = $item->getContext()->getTargetContext();
        if (null === $targetContext) {
            return null;
        }

        $entityClass = $targetContext->getClassName();
        if (!$entityClass || false === strpos($entityClass, '\\')) {
            return null;
        }

        try {
            return $targetContext->getMetadata();
        } catch (\Exception $e) {
            return null;
        }
    }
}
