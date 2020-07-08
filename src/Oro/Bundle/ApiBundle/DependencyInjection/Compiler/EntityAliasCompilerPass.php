<?php

namespace Oro\Bundle\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers all entity alias providers that are used only in API.
 */
class EntityAliasCompilerPass implements CompilerPassInterface
{
    use ApiTaggedServiceTrait;

    private const ENTITY_ALIAS_RESOLVER_REGISTRY_SERVICE_ID = 'oro_api.entity_alias_resolver_registry';

    private const ALIAS_PROVIDER_TAG_NAME = 'oro_entity.alias_provider';
    private const CLASS_PROVIDER_TAG_NAME = 'oro_entity.class_provider';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // find providers
        $classProviders = $this->getClassProviders($container);
        $aliasProviders = $this->getAliasProviders($container);

        // register
        $resolvers = $container->getDefinition(self::ENTITY_ALIAS_RESOLVER_REGISTRY_SERVICE_ID)->getArgument(0);
        foreach ($resolvers as $resolver) {
            $loaderServiceId = (string)$container->getDefinition($resolver[0])->getArgument(0);
            $resolverDef = $container->getDefinition($loaderServiceId);
            foreach ($classProviders as $classProvider) {
                $resolverDef->addMethodCall('addEntityClassProvider', [$classProvider]);
            }
            foreach ($aliasProviders as $aliasProvider) {
                $resolverDef->addMethodCall('addEntityAliasProvider', [$aliasProvider]);
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return string[]
     */
    private function getClassProviders(ContainerBuilder $container): array
    {
        $classProviders = [];
        $taggedServices = $container->findTaggedServiceIds(self::CLASS_PROVIDER_TAG_NAME);
        foreach ($taggedServices as $id => $tags) {
            $classProviders[] = new Reference($id);
        }

        return $classProviders;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return string[]
     */
    private function getAliasProviders(ContainerBuilder $container): array
    {
        $aliasProviders = [];
        $taggedServices = $container->findTaggedServiceIds(self::ALIAS_PROVIDER_TAG_NAME);
        foreach ($taggedServices as $id => $tags) {
            $aliasProviders[$this->getPriorityAttribute($tags[0])][] = new Reference($id);
        }
        if (!empty($aliasProviders)) {
            $aliasProviders = $this->sortByPriorityAndFlatten($aliasProviders);
        }

        return $aliasProviders;
    }
}
