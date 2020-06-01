<?php

namespace Oro\Bundle\DataGridBundle\EventListener;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Event\GridViewsLoadEvent;
use Oro\Bundle\DataGridBundle\Extension\GridViews\GridViewsExtension;
use Oro\Bundle\DataGridBundle\Extension\GridViews\View;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\SearchBundle\Datagrid\Datasource\SearchDatasource;
use Oro\Bundle\SearchBundle\Provider\AbstractSearchMappingProvider;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Sets label for default All grid view.
 */
class DefaultGridViewLoadListener
{
    /** @var EntityClassResolver */
    protected $entityClassResolver;

    /** @var AbstractSearchMappingProvider */
    protected $mappingProvider;

    /** @var ConfigManager */
    protected $configManager;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param EntityClassResolver $entityClassResolver
     * @param AbstractSearchMappingProvider $mappingProvider
     * @param ConfigManager $configManager
     * @param TranslatorInterface $translator
     */
    public function __construct(
        EntityClassResolver $entityClassResolver,
        AbstractSearchMappingProvider $mappingProvider,
        ConfigManager $configManager,
        TranslatorInterface $translator
    ) {
        $this->entityClassResolver = $entityClassResolver;
        $this->mappingProvider = $mappingProvider;
        $this->configManager = $configManager;
        $this->translator = $translator;
    }

    /**
     * @param GridViewsLoadEvent $event
     */
    public function onViewsLoad(GridViewsLoadEvent $event): void
    {
        $config = $event->getGridConfiguration();
        $gridViews = $event->getGridViews();

        /** @var View $gridView */
        foreach ($gridViews as $key => $gridView) {
            if (!isset($gridView['name']) || $gridView['name'] !== GridViewsExtension::DEFAULT_VIEW_ID) {
                continue;
            }

            $label = $this->getAllGridViewLabel($config);
            if ($label) {
                $gridViews[$key]['label'] = $label;
            }

            break;
        }

        $event->setGridViews($gridViews);
    }

    /**
     * @param DatagridConfiguration $config
     *
     * @return string
     */
    protected function getAllGridViewLabel(DatagridConfiguration $config): string
    {
        $entityClass = $this->getEntityClassNameFromQuery($config);
        $allLabelTranslationKey = $config->offsetGetByPath('[options][gridViews][allLabel]');
        $commonTranslationKey = '';
        $parameters = ['%entity_plural_label%' => $entityClass ? $this->getEntityPluralLabel($entityClass) : ''];

        if (!$allLabelTranslationKey && $entityClass) {
            $commonTranslationKey = $this->getAllGridViewTranslationKey($entityClass);
        }

        if ($allLabelTranslationKey || $commonTranslationKey) {
            $label = $this->translator->trans($allLabelTranslationKey ?: $commonTranslationKey, $parameters);

            if ($label !== $commonTranslationKey) {
                // Returns label for All grid view if allLabel option is specified or common translation key
                // is translated.
                return $label;
            }
        }

        return '';
    }

    /**
     * @param string $className
     *
     * @return string
     */
    protected function getEntityPluralLabel(string $className): string
    {
        $provider = $this->configManager->getProvider('entity');
        if (!$provider || !$provider->hasConfig($className)) {
            return '';
        }

        return $this->translator->trans($provider->getConfig($className)->get('plural_label', false, ''));
    }

    /**
     * @param string $className
     *
     * @return string
     */
    protected function getAllGridViewTranslationKey(string $className): string
    {
        $provider = $this->configManager->getProvider('entity');
        if (!$provider || !$provider->hasConfig($className)) {
            return '';
        }

        return $provider->getConfig($className)->get('grid_all_view_label', false, '');
    }

    /**
     * @param DatagridConfiguration $config
     *
     * @return string|null
     */
    protected function getEntityClassNameFromQuery(DatagridConfiguration $config): ?string
    {
        $entityClassName = null;
        if ($config->isOrmDatasource()) {
            $entityClassName = $config->getOrmQuery()->getRootEntity($this->entityClassResolver, true);
        } elseif (SearchDatasource::TYPE === $config->getDatasourceType()) {
            $fromPath = $config->offsetGetByPath(DatagridConfiguration::FROM_PATH);
            if ($fromPath) {
                $entityClassName = $this->mappingProvider->getEntityClass($fromPath[0]);
            }
        }

        return $entityClassName;
    }
}
