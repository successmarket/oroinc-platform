<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\ApiBundle\Config\Extension\ConfigExtensionRegistry;
use Oro\Bundle\ApiBundle\Config\Extension\FiltersConfigExtension;
use Oro\Bundle\ApiBundle\Config\Extension\SortersConfigExtension;
use Oro\Bundle\ApiBundle\DependencyInjection\OroApiExtension;
use Oro\Bundle\ApiBundle\Filter\FilterOperatorRegistry;
use Oro\Bundle\ApiBundle\Provider\CombinedConfigBag;
use Oro\Bundle\ApiBundle\Util\DependencyInjectionUtil;
use Oro\Component\Config\CumulativeResourceManager;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class OroApiExtensionTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $bundle1 = new Fixtures\BarBundle\BarBundle();
        $bundle2 = new Fixtures\BazBundle\BazBundle();
        $bundle3 = new Fixtures\FooBundle\FooBundle();
        CumulativeResourceManager::getInstance()
            ->clear()
            ->setBundles(
                [
                    $bundle1->getName() => get_class($bundle1),
                    $bundle2->getName() => get_class($bundle2),
                    $bundle3->getName() => get_class($bundle3)
                ]
            );
    }

    protected function tearDown(): void
    {
        CumulativeResourceManager::getInstance()->clear();
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $serviceId
     */
    private static function assertServiceExists(ContainerBuilder $container, $serviceId)
    {
        if (!$container->hasDefinition($serviceId)) {
            self::fail(sprintf('Service "%s" should be defined', $serviceId));
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $serviceId
     */
    private static function assertServiceNotExists(ContainerBuilder $container, $serviceId)
    {
        if ($container->hasDefinition($serviceId)) {
            self::fail(sprintf('Service "%s" should not be defined', $serviceId));
        }
    }

    /**
     * @param array            $serviceIds
     * @param object           $serviceLocatorReference
     * @param ContainerBuilder $container
     */
    private static function assertServiceLocator(
        array $serviceIds,
        $serviceLocatorReference,
        ContainerBuilder $container
    ) {
        $services = [];
        foreach ($serviceIds as $serviceId) {
            $services[$serviceId] = new ServiceClosureArgument(new Reference($serviceId));
        }

        self::assertInstanceOf(Reference::class, $serviceLocatorReference);
        $serviceLocatorDef = $container->getDefinition((string)$serviceLocatorReference);
        self::assertEquals(ServiceLocator::class, $serviceLocatorDef->getClass());
        self::assertEquals($services, $serviceLocatorDef->getArgument(0));
    }

    /**
     * @return ConfigExtensionRegistry
     */
    private function getConfigExtensionRegistry()
    {
        $configExtensionRegistry = new ConfigExtensionRegistry(3);
        $configExtensionRegistry->addExtension(new FiltersConfigExtension(new FilterOperatorRegistry([])));
        $configExtensionRegistry->addExtension(new SortersConfigExtension());

        return $configExtensionRegistry;
    }

    /**
     * @param bool $devMode
     *
     * @return ContainerBuilder
     */
    private function getContainer(bool $devMode = false)
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $devMode);
        $container->setParameter('kernel.environment', $devMode ? 'dev' : 'prod');
        $container->set('oro_api.config_extension_registry', $this->getConfigExtensionRegistry());

        return $container;
    }

    /**
     * @return array
     */
    public static function environmentDataProvider()
    {
        return [
            'prod mode' => [false],
            'dev mode'  => [true]
        ];
    }

    /**
     * @dataProvider environmentDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testLoadApiConfiguration(bool $devMode)
    {
        $container = $this->getContainer($devMode);
        $container->set('oro_api.config_extension_registry', $this->getConfigExtensionRegistry());

        $extension = new OroApiExtension();
        $extension->load([], $container);

        self::assertServiceExists($container, 'oro_api.config_bag_registry');
        if ($devMode) {
            self::assertServiceExists($container, 'oro_api.config_cache_state_registry');
            self::assertEquals(
                [['addDependency', [new Reference('oro_entity.entity_configuration.provider')]]],
                $container->getDefinition('oro_api.config_cache_factory')->getMethodCalls()
            );
            self::assertServiceExists($container, 'oro_api.config_cache_state.default');
        } else {
            self::assertServiceNotExists($container, 'oro_api.config_cache_state_registry');
            self::assertSame(
                [],
                $container->getDefinition('oro_api.config_cache_factory')->getMethodCalls()
            );
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.default');
        }
        self::assertServiceExists($container, 'oro_api.entity_exclusion_provider_registry');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver_registry');
        self::assertServiceExists($container, 'oro_api.entity_override_provider_registry');
        self::assertServiceExists($container, 'oro_api.config_bag.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.default');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.default');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.default');

        self::assertEquals(
            [
                ['oro_api.config_bag.default', '']
            ],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.config_bag.default'],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(1),
            $container
        );
        if ($devMode) {
            self::assertEquals(
                [
                    [new Reference('oro_api.config_cache_state.default'), '']
                ],
                $container->getDefinition('oro_api.config_cache_state_registry')->getArgument(0)
            );
        }
        self::assertEquals(
            [
                ['oro_api.chain_entity_exclusion_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.chain_entity_exclusion_provider.default'],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            ['api.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.default')->getArgument(4)
        );
        if ($devMode) {
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.default')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        } else {
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        }
        self::assertEquals(
            [
                ['oro_api.entity_alias_resolver.default', '']
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.entity_alias_resolver.default'],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            [
                ['oro_api.entity_override_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.entity_override_provider.default'],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(1),
            $container
        );

        self::assertEquals(
            [
                'default',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.default')->getArguments()
        );

        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_bag.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_alias_provider.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.default')->getArgument(1)
        );

        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_default']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.default')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.default')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.default')->getMethodCalls()
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_override_provider.default')->getArgument(0)
        );
    }

    /**
     * @dataProvider environmentDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testLoadApiConfigurationWithSeveralTypesOfConfigFilesInAdditionalToDefaultConfigFile(bool $devMode)
    {
        $container = $this->getContainer($devMode);
        $container->set('oro_api.config_extension_registry', $this->getConfigExtensionRegistry());

        $config1 = [
            'config_files' => [
                'first' => [
                    'file_name'    => 'api_first.yml',
                    'request_type' => ['first']
                ]
            ]
        ];
        $config2 = [
            'config_files' => [
                'second' => [
                    'file_name'    => 'api_second.yml',
                    'request_type' => ['second']
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config1, $config2], $container);

        self::assertServiceExists($container, 'oro_api.config_bag_registry');
        if ($devMode) {
            self::assertServiceExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceExists($container, 'oro_api.config_cache_state.first');
            self::assertServiceExists($container, 'oro_api.config_cache_state.second');
        } else {
            self::assertServiceNotExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.first');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.second');
        }
        self::assertServiceExists($container, 'oro_api.entity_exclusion_provider_registry');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver_registry');
        self::assertServiceExists($container, 'oro_api.entity_override_provider_registry');
        self::assertServiceExists($container, 'oro_api.config_bag.default');
        self::assertServiceExists($container, 'oro_api.config_bag.first');
        self::assertServiceExists($container, 'oro_api.config_bag.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.second');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.default');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.first');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.second');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.second');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.second');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.second');

        self::assertEquals(
            [
                ['oro_api.config_bag.first', 'first'],
                ['oro_api.config_bag.second', 'second'],
                ['oro_api.config_bag.default', '']
            ],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.config_bag.default', 'oro_api.config_bag.first', 'oro_api.config_bag.second'],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(1),
            $container
        );
        if ($devMode) {
            self::assertEquals(
                [
                    [new Reference('oro_api.config_cache_state.first'), 'first'],
                    [new Reference('oro_api.config_cache_state.second'), 'second'],
                    [new Reference('oro_api.config_cache_state.default'), '']
                ],
                $container->getDefinition('oro_api.config_cache_state_registry')->getArgument(0)
            );
        }
        self::assertEquals(
            [
                ['oro_api.chain_entity_exclusion_provider.first', 'first'],
                ['oro_api.chain_entity_exclusion_provider.second', 'second'],
                ['oro_api.chain_entity_exclusion_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.chain_entity_exclusion_provider.default',
                'oro_api.chain_entity_exclusion_provider.first',
                'oro_api.chain_entity_exclusion_provider.second'
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            ['api_first.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.first')->getArgument(4)
        );
        self::assertEquals(
            ['api_second.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.second')->getArgument(4)
        );
        self::assertEquals(
            ['api.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.default')->getArgument(4)
        );
        if ($devMode) {
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.first')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.first')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.second')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.second')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.default')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        } else {
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.first')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.second')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        }
        self::assertEquals(
            [
                ['oro_api.entity_alias_resolver.first', 'first'],
                ['oro_api.entity_alias_resolver.second', 'second'],
                ['oro_api.entity_alias_resolver.default', '']
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_alias_resolver.default',
                'oro_api.entity_alias_resolver.first',
                'oro_api.entity_alias_resolver.second'
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            [
                ['oro_api.entity_override_provider.first', 'first'],
                ['oro_api.entity_override_provider.second', 'second'],
                ['oro_api.entity_override_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_override_provider.default',
                'oro_api.entity_override_provider.first',
                'oro_api.entity_override_provider.second'
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(1),
            $container
        );

        self::assertEquals(
            [
                'default',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.default')->getArguments()
        );
        self::assertEquals(
            [
                'first',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.first')->getArguments()
        );
        self::assertEquals(
            [
                'second',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.second')->getArguments()
        );

        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_bag.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.config_bag.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.config_bag.second')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_alias_provider.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.entity_alias_provider.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.entity_alias_provider.second')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.default')->getArgument(1)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.first')->getArgument(1)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.second')->getArgument(1)
        );

        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_default']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_first']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_second']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.second')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.default')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.default')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.first')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.first')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.second')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.second')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.second')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.first')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.first')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.second')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.second')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.second')->getMethodCalls()
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_override_provider.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.entity_override_provider.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.entity_override_provider.second')->getArgument(0)
        );
    }

    /**
     * @dataProvider environmentDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testLoadApiConfigurationShouldBeSortedByRequestType(bool $devMode)
    {
        $container = $this->getContainer($devMode);
        $container->set('oro_api.config_extension_registry', $this->getConfigExtensionRegistry());

        $config = [
            'config_files' => [
                'default'               => [
                    'request_type' => []
                ],
                'test'                  => [
                    'file_name'    => 'api_test.yml',
                    'request_type' => ['test']
                ],
                'several_request_types' => [
                    'file_name'    => 'api_several_request_types.yml',
                    'request_type' => ['test1', 'test2']
                ],
                'another'               => [
                    'file_name'    => 'api_another.yml',
                    'request_type' => ['another']
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);

        self::assertServiceExists($container, 'oro_api.config_bag_registry');
        if ($devMode) {
            self::assertServiceExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceExists($container, 'oro_api.config_cache_state.test');
            self::assertServiceExists($container, 'oro_api.config_cache_state.another');
            self::assertServiceExists($container, 'oro_api.config_cache_state.several_request_types');
        } else {
            self::assertServiceNotExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.test');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.another');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.several_request_types');
        }
        self::assertServiceExists($container, 'oro_api.entity_exclusion_provider_registry');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver_registry');
        self::assertServiceExists($container, 'oro_api.entity_override_provider_registry');

        self::assertEquals(
            [
                ['oro_api.config_bag.several_request_types', 'test1&test2'],
                ['oro_api.config_bag.test', 'test'],
                ['oro_api.config_bag.another', 'another'],
                ['oro_api.config_bag.default', '']
            ],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.config_bag.default',
                'oro_api.config_bag.test',
                'oro_api.config_bag.several_request_types',
                'oro_api.config_bag.another'
            ],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(1),
            $container
        );
        if ($devMode) {
            self::assertEquals(
                [
                    [new Reference('oro_api.config_cache_state.several_request_types'), 'test1&test2'],
                    [new Reference('oro_api.config_cache_state.test'), 'test'],
                    [new Reference('oro_api.config_cache_state.another'), 'another'],
                    [new Reference('oro_api.config_cache_state.default'), '']
                ],
                $container->getDefinition('oro_api.config_cache_state_registry')->getArgument(0)
            );
        }
        self::assertEquals(
            [
                ['oro_api.chain_entity_exclusion_provider.several_request_types', 'test1&test2'],
                ['oro_api.chain_entity_exclusion_provider.test', 'test'],
                ['oro_api.chain_entity_exclusion_provider.another', 'another'],
                ['oro_api.chain_entity_exclusion_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.chain_entity_exclusion_provider.default',
                'oro_api.chain_entity_exclusion_provider.test',
                'oro_api.chain_entity_exclusion_provider.several_request_types',
                'oro_api.chain_entity_exclusion_provider.another'
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            ['api_several_request_types.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.several_request_types')->getArgument(4)
        );
        self::assertEquals(
            ['api_test.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.test')->getArgument(4)
        );
        self::assertEquals(
            ['api_another.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.another')->getArgument(4)
        );
        self::assertEquals(
            ['api.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.default')->getArgument(4)
        );
        if ($devMode) {
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.several_request_types')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.several_request_types')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.test')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.test')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.another')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.another')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.default')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        } else {
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.several_request_types')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.test')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.another')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        }
        self::assertEquals(
            [
                ['oro_api.entity_alias_resolver.several_request_types', 'test1&test2'],
                ['oro_api.entity_alias_resolver.test', 'test'],
                ['oro_api.entity_alias_resolver.another', 'another'],
                ['oro_api.entity_alias_resolver.default', '']
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_alias_resolver.default',
                'oro_api.entity_alias_resolver.test',
                'oro_api.entity_alias_resolver.several_request_types',
                'oro_api.entity_alias_resolver.another'
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            [
                ['oro_api.entity_override_provider.several_request_types', 'test1&test2'],
                ['oro_api.entity_override_provider.test', 'test'],
                ['oro_api.entity_override_provider.another', 'another'],
                ['oro_api.entity_override_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_override_provider.default',
                'oro_api.entity_override_provider.test',
                'oro_api.entity_override_provider.several_request_types',
                'oro_api.entity_override_provider.another'
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(1),
            $container
        );
    }

    /**
     * @dataProvider environmentDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testLoadApiConfigurationForConfigWithSeveralConfigFiles(bool $devMode)
    {
        $container = $this->getContainer($devMode);
        $container->set('oro_api.config_extension_registry', $this->getConfigExtensionRegistry());

        $config1 = [
            'config_files' => [
                'first' => [
                    'file_name'    => 'api_first.yml',
                    'request_type' => ['first']
                ]
            ]
        ];
        $config2 = [
            'config_files' => [
                'second' => [
                    'file_name'    => ['api_second.yml', 'api_first.yml'],
                    'request_type' => ['second']
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config1, $config2], $container);

        self::assertServiceExists($container, 'oro_api.config_bag_registry');
        if ($devMode) {
            self::assertServiceExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceExists($container, 'oro_api.config_cache_state.first');
            self::assertServiceExists($container, 'oro_api.config_cache_state.second');
        } else {
            self::assertServiceNotExists($container, 'oro_api.config_cache_state_registry');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.default');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.first');
            self::assertServiceNotExists($container, 'oro_api.config_cache_state.second');
        }
        self::assertServiceExists($container, 'oro_api.entity_exclusion_provider_registry');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver_registry');
        self::assertServiceExists($container, 'oro_api.entity_override_provider_registry');
        self::assertServiceExists($container, 'oro_api.config_bag.default');
        self::assertServiceExists($container, 'oro_api.config_bag.first');
        self::assertServiceExists($container, 'oro_api.config_bag.second');
        self::assertServiceExists($container, 'oro_api.config_bag.second_0_internal');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_cache.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_provider.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_loader.second');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.default');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.first');
        self::assertServiceExists($container, 'oro_api.entity_alias_resolver.second');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.default');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.first');
        self::assertServiceExists($container, 'oro_api.entity_override_provider.second');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.config_entity_exclusion_provider.second');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.aliased_entity_exclusion_provider.second');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.default');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.first');
        self::assertServiceExists($container, 'oro_api.chain_entity_exclusion_provider.second');

        self::assertEquals(
            [
                ['oro_api.config_bag.first', 'first'],
                ['oro_api.config_bag.second', 'second'],
                ['oro_api.config_bag.default', '']
            ],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            ['oro_api.config_bag.default', 'oro_api.config_bag.first', 'oro_api.config_bag.second'],
            $container->getDefinition('oro_api.config_bag_registry')->getArgument(1),
            $container
        );
        if ($devMode) {
            self::assertEquals(
                [
                    [new Reference('oro_api.config_cache_state.first'), 'first'],
                    [new Reference('oro_api.config_cache_state.second'), 'second'],
                    [new Reference('oro_api.config_cache_state.default'), '']
                ],
                $container->getDefinition('oro_api.config_cache_state_registry')->getArgument(0)
            );
        }
        self::assertEquals(
            [
                ['oro_api.chain_entity_exclusion_provider.first', 'first'],
                ['oro_api.chain_entity_exclusion_provider.second', 'second'],
                ['oro_api.chain_entity_exclusion_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.chain_entity_exclusion_provider.default',
                'oro_api.chain_entity_exclusion_provider.first',
                'oro_api.chain_entity_exclusion_provider.second'
            ],
            $container->getDefinition('oro_api.entity_exclusion_provider_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            ['api_first.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.first')->getArgument(4)
        );
        self::assertEquals(
            ['api_second.yml', 'api_first.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.second')->getArgument(4)
        );
        self::assertEquals(
            ['api.yml'],
            $container->getDefinition('oro_api.entity_alias_resolver.default')->getArgument(4)
        );
        if ($devMode) {
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.first')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.first')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.second')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.second')->getMethodCalls()
            );
            self::assertEquals(
                [['setConfigCacheState', [new Reference('oro_api.config_cache_state.default')]]],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        } else {
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.first')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.second')->getMethodCalls()
            );
            self::assertEquals(
                [],
                $container->getDefinition('oro_api.entity_alias_resolver.default')->getMethodCalls()
            );
        }
        self::assertEquals(
            [
                ['oro_api.entity_alias_resolver.first', 'first'],
                ['oro_api.entity_alias_resolver.second', 'second'],
                ['oro_api.entity_alias_resolver.default', '']
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_alias_resolver.default',
                'oro_api.entity_alias_resolver.first',
                'oro_api.entity_alias_resolver.second'
            ],
            $container->getDefinition('oro_api.entity_alias_resolver_registry')->getArgument(1),
            $container
        );
        self::assertEquals(
            [
                ['oro_api.entity_override_provider.first', 'first'],
                ['oro_api.entity_override_provider.second', 'second'],
                ['oro_api.entity_override_provider.default', '']
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(0)
        );
        self::assertServiceLocator(
            [
                'oro_api.entity_override_provider.default',
                'oro_api.entity_override_provider.first',
                'oro_api.entity_override_provider.second'
            ],
            $container->getDefinition('oro_api.entity_override_provider_registry')->getArgument(1),
            $container
        );

        self::assertEquals(
            [
                'default',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.default')->getArguments()
        );
        self::assertEquals(
            [
                'first',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.first')->getArguments()
        );
        self::assertEquals(
            [
                'second',
                '%kernel.debug%',
                new Reference('oro_api.config_cache_factory'),
                new Reference('oro_api.config_cache_warmer')
            ],
            $container->getDefinition('oro_api.config_cache.second')->getArguments()
        );

        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_bag.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.config_bag.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.config_bag.second_0_internal')->getArgument(0)
        );
        self::assertEquals(
            CombinedConfigBag::class,
            $container->getDefinition('oro_api.config_bag.second')->getClass()
        );
        self::assertEquals(
            [
                [
                    new Reference('oro_api.config_bag.second_0_internal'),
                    new Reference('oro_api.config_bag.second_1_internal')
                ],
                new Reference('oro_api.config_merger.entity')
            ],
            $container->getDefinition('oro_api.config_bag.second')->getArguments()
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_alias_provider.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.entity_alias_provider.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.entity_alias_provider.second')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.default')->getArgument(1)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.first')->getArgument(1)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.config_entity_exclusion_provider.second')->getArgument(1)
        );

        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_default']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_first']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['setNamespace', ['oro_api_aliases_second']]
            ],
            $container->getDefinition('oro_api.entity_alias_cache.second')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.default')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.default')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.first')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.first')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addEntityAliasProvider', [new Reference('oro_api.entity_alias_provider.second')]],
                ['addEntityClassProvider', [new Reference('oro_api.entity_alias_provider.second')]]
            ],
            $container->getDefinition('oro_api.entity_alias_loader.second')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.default')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.default')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.first')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.first')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.first')->getMethodCalls()
        );
        self::assertEquals(
            [
                ['addProvider', [new Reference('oro_api.config_entity_exclusion_provider.second')]],
                ['addProvider', [new Reference('oro_api.aliased_entity_exclusion_provider.second')]],
                ['addProvider', [new Reference('oro_api.entity_exclusion_provider.shared')]]
            ],
            $container->getDefinition('oro_api.chain_entity_exclusion_provider.second')->getMethodCalls()
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.default'),
            $container->getDefinition('oro_api.entity_override_provider.default')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.first'),
            $container->getDefinition('oro_api.entity_override_provider.first')->getArgument(0)
        );
        self::assertEquals(
            new Reference('oro_api.config_cache.second'),
            $container->getDefinition('oro_api.entity_override_provider.second')->getArgument(0)
        );
    }

    public function testLoadApiConfigurationShouldThrowExceptionIfExistSeveralConfigurationsWithSameRequestType()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "oro_api.config_files":'
            . ' The "request_type" options for "test1" and "test2" are duplicated.'
        );

        $container = $this->getContainer();

        $config = [
            'config_files' => [
                'test1' => [
                    'file_name'    => ['api_test1.yml'],
                    'request_type' => ['test1', 'test2']
                ],
                'test2' => [
                    'file_name'    => ['api_test2.yml'],
                    'request_type' => ['test2', 'test1']
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);
    }

    public function testLoadApiConfigurationShouldThrowExceptionIfExistConfigurationsWithSameRequestTypeAsDefaultOne()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "oro_api.config_files":'
            . ' The "request_type" options for "test2" and "default" are duplicated.'
        );

        $container = $this->getContainer();

        $config = [
            'config_files' => [
                'test1' => [
                    'file_name'    => ['api_test1.yml'],
                    'request_type' => ['test1']
                ],
                'test2' => [
                    'file_name'    => ['api_test2.yml'],
                    'request_type' => []
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);
    }

    public function testRegisterConfigParameters()
    {
        $container = $this->getContainer();

        $config = [
            'config_max_nesting_level' => 2,
            'config_files'             => [
                'first'  => [
                    'file_name'    => 'api_first.yml',
                    'request_type' => ['first']
                ],
                'second' => [
                    'file_name'    => ['api_second.yml', 'api_first.yml'],
                    'request_type' => ['second']
                ]
            ],
            'api_doc_views'            => [
                'view_1'       => [
                    'label'        => 'View 1',
                    'request_type' => ['first', 'rest']
                ],
                'default_view' => [
                    'label'   => 'Default View',
                    'default' => true
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);

        self::assertEquals(
            ['view_1', 'default_view'],
            $container->getParameter('oro_api.api_doc.views')
        );
        self::assertEquals(
            'default_view',
            $container->getParameter('oro_api.api_doc.default_view')
        );

        self::assertServiceExists($container, 'oro_api.config_extension_registry');
        self::assertEquals(
            2,
            $container->getDefinition('oro_api.config_extension_registry')->getArgument(0)
        );

        self::assertServiceExists($container, 'oro_api.config_cache_warmer');
        self::assertEquals(
            [
                'first'   => ['api_first.yml'],
                'second'  => ['api_second.yml', 'api_first.yml'],
                'default' => ['api.yml']
            ],
            $container->getDefinition('oro_api.config_cache_warmer')->getArgument(0)
        );

        self::assertServiceExists($container, 'oro_api.cache_manager');
        self::assertEquals(
            [
                'first'   => ['first'],
                'second'  => ['second'],
                'default' => []
            ],
            $container->getDefinition('oro_api.cache_manager')->getArgument(0)
        );
        self::assertEquals(
            [
                'view_1'       => ['first', 'rest'],
                'default_view' => []
            ],
            $container->getDefinition('oro_api.cache_manager')->getArgument(1)
        );
    }

    public function testConfigurationForEmptyCors()
    {
        $container = $this->getContainer();

        $config = [];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);

        self::assertSame(
            600,
            $container->getDefinition('oro_api.options.rest.set_cache_control')->getArgument(0)
        );
        self::assertSame(
            600,
            $container->getDefinition('oro_api.options.rest.cors.set_max_age')->getArgument(0)
        );
        self::assertSame(
            [],
            $container->getDefinition('oro_api.rest.cors.set_allow_origin')->getArgument(0)
        );
        self::assertSame(
            [],
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(0)
        );
        self::assertSame(
            [],
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(1)
        );
        self::assertSame(
            false,
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(2)
        );
    }

    public function testConfigurationForCors()
    {
        $container = $this->getContainer();

        $config = [
            'cors' => [
                'preflight_max_age' => 123,
                'allow_origins'     => ['https://foo.com'],
                'allow_headers'     => ['AllowHeader1'],
                'expose_headers'    => ['ExposeHeader1'],
                'allow_credentials' => true
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);

        self::assertSame(
            $config['cors']['preflight_max_age'],
            $container->getDefinition('oro_api.options.rest.set_cache_control')->getArgument(0)
        );
        self::assertSame(
            $config['cors']['preflight_max_age'],
            $container->getDefinition('oro_api.options.rest.cors.set_max_age')->getArgument(0)
        );
        self::assertSame(
            $config['cors']['allow_origins'],
            $container->getDefinition('oro_api.rest.cors.set_allow_origin')->getArgument(0)
        );
        self::assertSame(
            $config['cors']['allow_headers'],
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(0)
        );
        self::assertSame(
            $config['cors']['expose_headers'],
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(1)
        );
        self::assertSame(
            $config['cors']['allow_credentials'],
            $container->getDefinition('oro_api.rest.cors.set_allow_and_expose_headers')->getArgument(2)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testConfigurationForApiDocViews()
    {
        $container = $this->getContainer();

        $config = [
            'documentation_path' => 'http://test.com/default_api_docs',
            'api_doc_views'      => [
                'view1' => [
                    'label'        => 'View 1',
                    'default'      => true,
                    'request_type' => ['rest', 'json_api'],
                    'headers'      => [
                        'Content-Type' => 'application/vnd.api+json',
                        'X-Include'    => [
                            ['value' => 'totalCount', 'actions' => ['get_list']],
                            ['value' => 'forAllActions']
                        ]
                    ],
                    'data_types'   => [
                        'guid'     => 'string',
                        'currency' => 'string'
                    ]
                ],
                'view2' => [
                    'documentation_path' => 'http://test.com/api_docs_for_view2',
                    'html_formatter'     => 'another_html_formatter',
                    'sandbox'            => false
                ],
                'view3' => [],
                'view4' => [
                    'label'           => 'View 4',
                    'default'         => false,
                    'request_type'    => ['rest', 'json_api', 'api4'],
                    'underlying_view' => 'view1',
                    'headers'         => [
                        'X-Include' => [
                            ['value' => 'totalCount', 'actions' => ['get_list', 'delete_list']],
                            ['value' => 'another']
                        ]
                    ],
                    'data_types'      => [
                        'currency' => 'decimal',
                        'percent'  => 'float'
                    ]
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load([$config], $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'view1' => [
                    'label'              => 'View 1',
                    'default'            => true,
                    'request_type'       => ['rest', 'json_api'],
                    'documentation_path' => 'http://test.com/default_api_docs',
                    'html_formatter'     => 'oro_api.api_doc.formatter.html_formatter',
                    'sandbox'            => true,
                    'headers'            => [
                        'Content-Type' => [
                            ['value' => 'application/vnd.api+json', 'actions' => []]
                        ],
                        'X-Include'    => [
                            ['value' => 'totalCount', 'actions' => ['get_list']],
                            ['value' => 'forAllActions', 'actions' => []]
                        ]
                    ],
                    'data_types'         => [
                        'guid'     => 'string',
                        'currency' => 'string'
                    ]
                ],
                'view2' => [
                    'default'            => false,
                    'request_type'       => [],
                    'documentation_path' => 'http://test.com/api_docs_for_view2',
                    'html_formatter'     => 'another_html_formatter',
                    'sandbox'            => false,
                    'headers'            => []
                ],
                'view3' => [
                    'default'            => false,
                    'request_type'       => [],
                    'documentation_path' => 'http://test.com/default_api_docs',
                    'html_formatter'     => 'oro_api.api_doc.formatter.html_formatter',
                    'sandbox'            => true,
                    'headers'            => []
                ],
                'view4' => [
                    'label'              => 'View 4',
                    'default'            => false,
                    'request_type'       => ['rest', 'json_api', 'api4'],
                    'underlying_view'    => 'view1',
                    'documentation_path' => 'http://test.com/default_api_docs',
                    'html_formatter'     => 'oro_api.api_doc.formatter.html_formatter',
                    'sandbox'            => true,
                    'headers'            => [
                        'Content-Type' => [
                            ['value' => 'application/vnd.api+json', 'actions' => []]
                        ],
                        'X-Include'    => [
                            ['value' => 'totalCount', 'actions' => ['get_list', 'delete_list']],
                            ['value' => 'another', 'actions' => []],
                            ['value' => 'forAllActions', 'actions' => []]
                        ]
                    ],
                    'data_types'         => [
                        'guid'     => 'string',
                        'currency' => 'decimal',
                        'percent'  => 'float'
                    ]
                ]
            ],
            $apiConfig['api_doc_views']
        );
    }

    public function testConfigurationForDefaultApiDocCache()
    {
        $container = $this->getContainer();

        $extension = new OroApiExtension();
        $extension->load([], $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'excluded_features' => ['web_api']
            ],
            $apiConfig['api_doc_cache']
        );
    }

    public function testConfigurationForApiDocCache()
    {
        $container = $this->getContainer();

        $configs = [
            [
                'api_doc_cache' => [
                    'excluded_features' => ['feature1']
                ]
            ],
            [
                'api_doc_cache' => [
                    'excluded_features' => ['feature2']
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load($configs, $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'excluded_features' => ['feature1', 'feature2']
            ],
            $apiConfig['api_doc_cache']
        );
    }

    public function testConfigurationForDefaultFeatureDependedFirewalls()
    {
        $container = $this->getContainer();

        $extension = new OroApiExtension();
        $extension->load([], $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals([], $apiConfig['api_firewalls']);
    }

    public function testConfigurationForFeatureDependedFirewalls()
    {
        $container = $this->getContainer();

        $configs = [
            [
                'api_firewalls' => [
                    'firewall1' => [
                        'feature_name' => 'feature1'
                    ],
                    'firewall2' => [
                        'feature_name'               => 'feature2',
                        'feature_firewall_listeners' => ['firewall2_listener1']
                    ],
                    'firewall3' => [
                        'feature_name'               => 'feature3',
                        'feature_firewall_listeners' => ['firewall3_listener1']
                    ],
                    'firewall4' => [
                        'feature_name' => 'feature4'
                    ]
                ]
            ],
            [
                'api_firewalls' => [
                    'firewall2' => [
                        'feature_firewall_listeners' => ['firewall2_listener2']
                    ],
                    'firewall4' => [
                        'feature_firewall_listeners' => ['firewall4_listener1']
                    ],
                    'firewall5' => [
                        'feature_name' => 'feature5'
                    ]
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load($configs, $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'firewall1' => [
                    'feature_name'               => 'feature1',
                    'feature_firewall_listeners' => []
                ],
                'firewall2' => [
                    'feature_name'               => 'feature2',
                    'feature_firewall_listeners' => ['firewall2_listener1', 'firewall2_listener2']
                ],
                'firewall3' => [
                    'feature_name'               => 'feature3',
                    'feature_firewall_listeners' => ['firewall3_listener1']
                ],
                'firewall4' => [
                    'feature_name'               => 'feature4',
                    'feature_firewall_listeners' => ['firewall4_listener1']
                ],
                'firewall5' => [
                    'feature_name'               => 'feature5',
                    'feature_firewall_listeners' => []
                ]
            ],
            $apiConfig['api_firewalls']
        );
    }

    public function testDefaultBatchApiConfiguration()
    {
        $container = $this->getContainer();

        $extension = new OroApiExtension();
        $extension->load([], $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'async_operation'                     => [
                    'lifetime'                => 30,
                    'cleanup_process_timeout' => 3600
                ],
                'chunk_size'                          => 100,
                'chunk_size_per_entity'               => [],
                'included_data_chunk_size'            => 50,
                'included_data_chunk_size_per_entity' => []
            ],
            $apiConfig['batch_api']
        );
    }

    public function testBatchApiConfiguration()
    {
        $container = $this->getContainer();

        $configs = [
            [
                'batch_api' => [
                    'chunk_size'               => 200,
                    'included_data_chunk_size' => 2000
                ]
            ],
            [
                'batch_api' => [
                    'async_operation'                     => [
                        'lifetime' => 40
                    ],
                    'chunk_size_per_entity'               => [
                        'Test\Entity1' => 10,
                        'Test\Entity2' => null,
                        'Test\Entity3' => null,
                        'Test\Entity4' => 40
                    ],
                    'included_data_chunk_size_per_entity' => [
                        'Test\Entity1' => 100,
                        'Test\Entity2' => null,
                        'Test\Entity3' => null,
                        'Test\Entity4' => 400
                    ]
                ]
            ],
            [
                'batch_api' => [
                    'async_operation'                     => [
                        'cleanup_process_timeout' => 3800
                    ],
                    'chunk_size_per_entity'               => [
                        'Test\Entity1' => 15,
                        'Test\Entity3' => 35,
                        'Test\Entity5' => 50
                    ],
                    'included_data_chunk_size_per_entity' => [
                        'Test\Entity1' => 150,
                        'Test\Entity3' => 350,
                        'Test\Entity5' => 500
                    ]
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load($configs, $container);

        $apiConfig = DependencyInjectionUtil::getConfig($container);
        self::assertEquals(
            [
                'async_operation'                     => [
                    'lifetime'                => 40,
                    'cleanup_process_timeout' => 3800
                ],
                'chunk_size'                          => 200,
                'chunk_size_per_entity'               => [
                    'Test\Entity1' => 15,
                    'Test\Entity3' => 35,
                    'Test\Entity4' => 40,
                    'Test\Entity5' => 50
                ],
                'included_data_chunk_size'            => 2000,
                'included_data_chunk_size_per_entity' => [
                    'Test\Entity1' => 150,
                    'Test\Entity3' => 350,
                    'Test\Entity4' => 400,
                    'Test\Entity5' => 500
                ]
            ],
            $apiConfig['batch_api']
        );
    }

    public function testBatchApiConfigurationWithNotIntegerValueForEntityChunkSize()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "oro_api.batch_api.chunk_size_per_entity.Test\Entity1":'
            . ' Expected int or NULL.'
        );

        $container = $this->getContainer();

        $configs = [
            [
                'batch_api' => [
                    'chunk_size_per_entity' => [
                        'Test\Entity1' => '123'
                    ]
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load($configs, $container);
    }

    public function testBatchApiConfigurationWithNotIntegerValueForIncludedEntityChunkSize()
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Invalid configuration for path "oro_api.batch_api.included_data_chunk_size_per_entity.Test\Entity1":'
            . ' Expected int or NULL.'
        );

        $container = $this->getContainer();

        $configs = [
            [
                'batch_api' => [
                    'included_data_chunk_size_per_entity' => [
                        'Test\Entity1' => '123'
                    ]
                ]
            ]
        ];

        $extension = new OroApiExtension();
        $extension->load($configs, $container);
    }
}
