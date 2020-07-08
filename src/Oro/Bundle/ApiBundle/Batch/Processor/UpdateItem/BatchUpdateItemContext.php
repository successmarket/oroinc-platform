<?php

namespace Oro\Bundle\ApiBundle\Batch\Processor\UpdateItem;

use Oro\Bundle\ApiBundle\Batch\Model\BatchSummary;
use Oro\Bundle\ApiBundle\Processor\ByStepNormalizeResultContext;
use Oro\Bundle\ApiBundle\Processor\Context;
use Oro\Component\ChainProcessor\ActionProcessorInterface;
use Oro\Component\ChainProcessor\ParameterBagInterface;

/**
 * The context for the "batch_update_item" action.
 */
class BatchUpdateItemContext extends ByStepNormalizeResultContext
{
    /** FQCN of an entity */
    private const CLASS_NAME = 'class';

    /** the name of the target action */
    private const TARGET_ACTION = 'targetAction';

    /** @var mixed */
    private $id;

    /** @var BatchSummary */
    private $summary;

    /** @var string[] */
    private $supportedEntityClasses = [];

    /** @var array|null */
    private $requestData;

    /** @var ActionProcessorInterface|null */
    private $targetProcessor;

    /** @var Context|null */
    private $targetContext;

    /** @var ParameterBagInterface|null */
    private $sharedData;

    /**
     * Gets FQCN of an entity.
     *
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->get(self::CLASS_NAME);
    }

    /**
     * Sets FQCN of an entity.
     *
     * @param string|null $className
     */
    public function setClassName(string $className = null): void
    {
        if (null === $className) {
            $this->remove(self::CLASS_NAME);
        } else {
            $this->set(self::CLASS_NAME, $className);
        }
    }

    /**
     * Gets an identifier of an entity.
     *
     * @return mixed|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets an identifier of an entity.
     *
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * Gets the summary statistics of this batch operation.
     *
     * @return BatchSummary|null
     */
    public function getSummary(): ?BatchSummary
    {
        return $this->summary;
    }

    /**
     * Sets the summary statistics of this batch operation.
     *
     * @param BatchSummary|null $summary
     */
    public function setSummary(?BatchSummary $summary): void
    {
        $this->summary = $summary;
    }

    /**
     * Gets entity classes supported by this batch operation.
     *
     * @return string[] The list of supported entity classes.
     *                  or empty array if any entities can be processed by this batch operation.
     */
    public function getSupportedEntityClasses(): array
    {
        return $this->supportedEntityClasses;
    }

    /**
     * Sets entity classes supported by this batch operation.
     *
     * @param string[] $supportedEntityClasses
     */
    public function setSupportedEntityClasses(array $supportedEntityClasses): void
    {
        $this->supportedEntityClasses = $supportedEntityClasses;
    }

    /**
     * Gets the request data.
     *
     * @return array|null
     */
    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    /**
     * Sets the request data.
     *
     * @param array|null $requestData
     */
    public function setRequestData(array $requestData = null): void
    {
        $this->requestData = $requestData;
    }

    /**
     * Gets the name of the target action.
     *
     * @return string|null
     */
    public function getTargetAction(): ?string
    {
        return $this->get(self::TARGET_ACTION);
    }

    /**
     * Sets the name of the target action.
     *
     * @param string|null $action
     */
    public function setTargetAction(string $action = null): void
    {
        if (null === $action) {
            $this->remove(self::TARGET_ACTION);
        } else {
            $this->set(self::TARGET_ACTION, $action);
        }
    }

    /**
     * Gets the processor responsible to process the request data.
     *
     * @return ActionProcessorInterface|null
     */
    public function getTargetProcessor(): ?ActionProcessorInterface
    {
        return $this->targetProcessor;
    }

    /**
     * Sets the processor responsible to process the request data.
     *
     * @param ActionProcessorInterface|null $processor
     */
    public function setTargetProcessor(ActionProcessorInterface $processor = null): void
    {
        $this->targetProcessor = $processor;
    }

    /**
     * Gets the context which should be used when processing the request data.
     *
     * @return Context|null
     */
    public function getTargetContext(): ?Context
    {
        return $this->targetContext;
    }

    /**
     * Sets the context which should be used when processing the request data.
     *
     * @param Context|null $context
     */
    public function setTargetContext(Context $context = null): void
    {
        $this->targetContext = $context;
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
}
