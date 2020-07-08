<?php

namespace Oro\Bundle\ApiBundle\Processor;

use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Component\ChainProcessor\ContextInterface as ComponentContextInterface;
use Oro\Component\ChainProcessor\ParameterBagInterface;

/**
 * Represents an execution context for API processors that can share date between a primary action
 * and actions that are executed as part of this primary action.
 */
interface SharedDataAwareContextInterface extends ComponentContextInterface
{
    /**
     * Gets the current request type.
     * A request can belong to several types, e.g. "rest" and "json_api".
     *
     * @return RequestType
     */
    public function getRequestType();

    /**
     * Gets API version
     *
     * @return string
     */
    public function getVersion();

    /**
     * Sets API version
     *
     * @param string $version
     */
    public function setVersion($version);

    /**
     * Gets an object that is used to share data between a primary action
     * and actions that are executed as part of this action.
     * Also, this object can be used to share data between different kind of child actions.
     *
     * @return ParameterBagInterface
     */
    public function getSharedData(): ParameterBagInterface;

    /**
     * Sets an object that is used to share data between a primary action
     * and actions that are executed as part of this action.
     * Also, this object can be used to share data between different kind of child actions.
     *
     * @param ParameterBagInterface $sharedData
     */
    public function setSharedData(ParameterBagInterface $sharedData): void;
}
