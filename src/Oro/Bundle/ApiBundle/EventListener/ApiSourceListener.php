<?php

namespace Oro\Bundle\ApiBundle\EventListener;

use Oro\Bundle\ApiBundle\Provider\CacheManager;
use Oro\Bundle\FeatureToggleBundle\Event\FeaturesChange;

/**
 * The event listener that is used to clear ApiDoc cache.
 */
class ApiSourceListener
{
    /** @var CacheManager */
    private $cacheManager;

    /** @var string[] */
    private $excludedFeatures;

    /**
     * @param CacheManager $cacheManager
     * @param string[]     $excludedFeatures
     */
    public function __construct(CacheManager $cacheManager, array $excludedFeatures)
    {
        $this->cacheManager = $cacheManager;
        $this->excludedFeatures = $excludedFeatures;
    }

    public function clearCache(): void
    {
        // clear all api caches data
        $this->cacheManager->clearCaches();
        // clear the cache for API documentation
        $this->cacheManager->clearApiDocCache();
    }

    /**
     * @param FeaturesChange $event
     */
    public function onFeaturesChange(FeaturesChange $event): void
    {
        // do not clear the cache if only excluded features are changed
        $numberOfChangedExcludedFeatures = 0;
        $changeSet = $event->getChangeSet();
        foreach ($this->excludedFeatures as $featureName) {
            if (\array_key_exists($featureName, $changeSet)) {
                $numberOfChangedExcludedFeatures++;
            }
        }
        if (0 === $numberOfChangedExcludedFeatures || count($changeSet) > $numberOfChangedExcludedFeatures) {
            $this->clearCache();
        }
    }
}
