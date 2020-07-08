<?php

namespace Oro\Bundle\MessageQueueBundle;

use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\AddTopicMetaPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildDestinationMetaRegistryPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildExtensionsPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildMessageProcessorRegistryPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildMonologHandlersPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildRouteRegistryPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\BuildTopicMetaSubscribersPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\ConfigureClearersPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\ConfigureDbalTransportExtensionsPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\MakeAnnotationReaderServicesPersistentPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\MakeLoggerServicesPersistentPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler\ProcessorLocatorPass;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\OroMessageQueueExtension;
use Oro\Bundle\MessageQueueBundle\DependencyInjection\Transport\Factory\DbalTransportFactory;
use Oro\Component\DependencyInjection\Compiler\PriorityNamedTaggedServiceWithHandlerCompilerPass;
use Oro\Component\DependencyInjection\Compiler\TaggedServiceTrait;
use Oro\Component\MessageQueue\Event\AfterSaveJobEvent;
use Oro\Component\MessageQueue\Event\BeforeSaveJobEvent;
use Oro\Component\MessageQueue\Job\Topics;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * OroMessageQueueBundle incorporates the OroMessageQueue component into OroPlatform
 * and thereby provides message queue processing capabilities for all application components
 */
class OroMessageQueueBundle extends Bundle
{
    use TaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ConfigureDbalTransportExtensionsPass());
        $container->addCompilerPass(new BuildExtensionsPass());
        $container->addCompilerPass(new BuildRouteRegistryPass());
        $container->addCompilerPass(new BuildMessageProcessorRegistryPass());
        $container->addCompilerPass(new BuildTopicMetaSubscribersPass());
        $container->addCompilerPass(new BuildDestinationMetaRegistryPass());
        $container->addCompilerPass(new BuildMonologHandlersPass());
        $container->addCompilerPass(new ConfigureClearersPass());
        $container->addCompilerPass(new MakeLoggerServicesPersistentPass());
        $container->addCompilerPass(new MakeAnnotationReaderServicesPersistentPass());
        $container->addCompilerPass(new ProcessorLocatorPass());
        $container->addCompilerPass(new PriorityNamedTaggedServiceWithHandlerCompilerPass(
            'oro_message_queue.client.message_filter',
            'oro_message_queue.message_filter',
            function (array $attributes, string $serviceId): array {
                return [$serviceId, $this->getAttribute($attributes, 'topic')];
            }
        ));

        /** @var OroMessageQueueExtension $extension */
        $extension = $container->getExtension('oro_message_queue');
        $extension->addTransportFactory(new DbalTransportFactory());

        $addTopicPass = AddTopicMetaPass::create()
            ->add(Topics::CALCULATE_ROOT_JOB_STATUS, 'Calculate root job status')
            ->add(Topics::ROOT_JOB_STOPPED, 'Root job stopped');
        $container->addCompilerPass($addTopicPass);

        $container->addCompilerPass(new AddEventAliasesPass([
            BeforeSaveJobEvent::class => BeforeSaveJobEvent::EVENT_ALIAS,
            AfterSaveJobEvent::class => AfterSaveJobEvent::EVENT_ALIAS,
        ]));
    }
}
