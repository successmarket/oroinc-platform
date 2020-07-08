<?php

namespace Oro\Bundle\WorkflowBundle\Serializer\Handler;

use JMS\Serializer\Context;
use JMS\Serializer\Visitor\SerializationVisitorInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\WorkflowBundle\Model\WorkflowResult;

/**
 * Provides the workflow result in the desired format
 */
class WorkflowResultHandler
{
    /**
     * @var DoctrineHelper $doctrineHelper
     */
    protected $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * @param SerializationVisitorInterface $visitor
     * @param WorkflowResult $workflowResult
     * @param array $type
     * @param $context
     * @return array
     */
    public function workflowResultToJson(
        SerializationVisitorInterface $visitor,
        WorkflowResult $workflowResult,
        array $type,
        Context $context
    ) {
        $result = $this->convertToPlainArray($workflowResult->getValues());
        if (!$result) {
            return null;
        }
        return (object)$result;
    }

    /**
     * @param array|\Traversable $values
     * @return array
     */
    protected function convertToPlainArray($values)
    {
        $result = array();
        foreach ($values as $key => $value) {
            if (is_object($value) && $this->doctrineHelper->isManageableEntity($value)) {
                $result[$key] = $this->doctrineHelper->getEntityIdentifier($value);
            } elseif (is_array($value) || $value instanceof \Traversable) {
                $result[$key] = $this->convertToPlainArray($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
