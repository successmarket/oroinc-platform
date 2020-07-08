<?php

namespace Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions;

use Oro\Bundle\ApiBundle\Request\RequestType;

/**
 * The class that helps to process different placeholders in a text.
 */
class DescriptionProcessor
{
    /** @var RequestDependedTextProcessor */
    private $requestDependedTextProcessor;

    /** @var FeatureDependedTextProcessor */
    private $featureDependedTextProcessor;

    /**
     * @param RequestDependedTextProcessor $requestDependedTextProcessor
     * @param FeatureDependedTextProcessor $featureDependedTextProcessor
     */
    public function __construct(
        RequestDependedTextProcessor $requestDependedTextProcessor,
        FeatureDependedTextProcessor $featureDependedTextProcessor
    ) {
        $this->requestDependedTextProcessor = $requestDependedTextProcessor;
        $this->featureDependedTextProcessor = $featureDependedTextProcessor;
    }

    /**
     * @param string      $description
     * @param RequestType $requestType
     *
     * @return string
     */
    public function process(string $description, RequestType $requestType): string
    {
        return $this->requestDependedTextProcessor->process(
            $this->featureDependedTextProcessor->process($description),
            $requestType
        );
    }
}
