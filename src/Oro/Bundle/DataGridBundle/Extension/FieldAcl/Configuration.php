<?php

namespace Oro\Bundle\DataGridBundle\Extension\FieldAcl;

use Oro\Bundle\DataGridBundle\Extension\Formatter\Property\PropertyInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const FIELDS_ACL = '[fields_acl]';
    const COLUMNS_PATH  = '[fields_acl][columns]';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('fields_acl');

        $builder->getRootNode()
            ->children()
                ->arrayNode('columns')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->treatFalseLike([PropertyInterface::DISABLED_KEY => true])
                        ->treatTrueLike([PropertyInterface::DISABLED_KEY => false])
                        ->treatNullLike([PropertyInterface::DISABLED_KEY => false])
                        ->children()
                            ->scalarNode(PropertyInterface::DATA_NAME_KEY)->end()
                            ->booleanNode(PropertyInterface::DISABLED_KEY)->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}
