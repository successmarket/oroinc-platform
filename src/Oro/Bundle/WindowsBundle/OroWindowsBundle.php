<?php

namespace Oro\Bundle\WindowsBundle;

use Oro\Component\DependencyInjection\Compiler\PriorityNamedTaggedServiceCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The WindowsBundle bundle class.
 */
class OroWindowsBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new PriorityNamedTaggedServiceCompilerPass(
            'oro_windows.manager.windows_state_registry',
            'oro_windows.windows_state_manager',
            'user_class'
        ));
    }
}
