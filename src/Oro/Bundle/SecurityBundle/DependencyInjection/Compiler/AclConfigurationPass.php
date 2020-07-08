<?php

namespace Oro\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Oro\Bundle\SecurityBundle\Acl\Cache\AclCache;
use Oro\Bundle\SecurityBundle\Acl\Dbal\MutableAclProvider;
use Oro\Bundle\SecurityBundle\Acl\Domain\SecurityIdentityRetrievalStrategy;
use Oro\Component\DependencyInjection\Compiler\TaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Configures ACL related services.
 */
class AclConfigurationPass implements CompilerPassInterface
{
    use TaggedServiceTrait;

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->substituteSecurityIdentityStrategy($container);
        $this->configureAclExtensionSelector($container);
        $this->configureDefaultAclProvider($container);
        $this->configureDefaultAclVoter($container);
        $this->configureAclCache($container);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function substituteSecurityIdentityStrategy(ContainerBuilder $container): void
    {
        $container->setDefinition(
            'security.acl.security_identity_retrieval_strategy',
            new Definition(SecurityIdentityRetrievalStrategy::class)
        );
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureAclExtensionSelector(ContainerBuilder $container): void
    {
        $extensions = [];
        $taggedServices = $container->findTaggedServiceIds('oro_security.acl.extension');
        foreach ($taggedServices as $id => $tags) {
            $extensions[$this->getPriorityAttribute($tags[0])][] = new Reference($id);
        }
        $extensions = $this->inverseSortByPriorityAndFlatten($extensions);

        $container->getDefinition('oro_security.acl.extension_selector')
            ->setArgument(0, $extensions);
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureDefaultAclProvider(ContainerBuilder $container): void
    {
        $container->getDefinition('security.acl.dbal.provider')
            ->setClass(MutableAclProvider::class)
            ->addMethodCall(
                'setSecurityIdentityToStringConverter',
                [new Reference('oro_security.acl.security_identity_to_string_converter')]
            );
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureDefaultAclVoter(ContainerBuilder $container): void
    {
        $voterDef = $container->getDefinition('security.acl.voter.basic_permissions');
        // substitute the ACL Provider and set the default ACL Provider as a base provider for new ACL Provider
        $newProviderId = 'oro_security.acl.provider';
        $newProviderDef = $container->getDefinition($newProviderId);
        $newProviderDef->addMethodCall('setBaseAclProvider', [$voterDef->getArgument(0)]);
        $voterDef->replaceArgument(0, new Reference($newProviderId));
    }

    /**
     * @param ContainerBuilder $container
     */
    private function configureAclCache(ContainerBuilder $container): void
    {
        $container->getDefinition('security.acl.cache.doctrine')
            ->setClass(AclCache::class)
            ->setArguments([
                new Reference('security.acl.cache.doctrine.cache_impl'),
                new Reference('oro_security.acl.permission_granting_strategy'),
                new Reference('security.acl.underlying.cache'),
                new Reference('event_dispatcher'),
                new Reference('oro_security.acl.security_identity_to_string_converter')
            ]);
    }
}
