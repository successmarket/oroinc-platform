<?php

namespace Oro\Bundle\ApiBundle\Processor\Subresource\Shared;

use Oro\Bundle\ApiBundle\Processor\Subresource\SubresourceContext;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Acl\Extension\EntityAclExtension;
use Oro\Bundle\SecurityBundle\Acl\Extension\ObjectIdentityHelper;
use Oro\Bundle\SecurityBundle\Acl\Group\AclGroupProviderInterface;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Validates whether an access to the type of entities specified
 * in the "parentClass" property of the context is granted.
 * The permission type is provided in $permission argument of the class constructor.
 */
class ParentEntityTypeSecurityCheck implements ProcessorInterface
{
    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var string */
    private $permission;

    /** @var bool */
    private $forcePermissionUsage;

    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var AclGroupProviderInterface */
    private $aclGroupProvider;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param DoctrineHelper                $doctrineHelper
     * @param AclGroupProviderInterface     $aclGroupProvider
     * @param string                        $permission
     * @param bool                          $forcePermissionUsage
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        DoctrineHelper $doctrineHelper,
        AclGroupProviderInterface $aclGroupProvider,
        $permission,
        $forcePermissionUsage = false
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->doctrineHelper = $doctrineHelper;
        $this->aclGroupProvider = $aclGroupProvider;
        $this->permission = $permission;
        $this->forcePermissionUsage = $forcePermissionUsage;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var SubresourceContext $context */

        $parentConfig = $context->getParentConfig();

        $isGranted = true;
        if ($parentConfig && $parentConfig->hasAclResource()) {
            $aclResource = $parentConfig->getAclResource();
            if ($aclResource) {
                if ($this->forcePermissionUsage) {
                    $isGranted = $isGranted = $this->isGrantedForClass($context);
                } else {
                    $isGranted = $this->authorizationChecker->isGranted($aclResource);
                }
            }
        } else {
            $isGranted = $isGranted = $this->isGrantedForClass($context);
        }

        if (!$isGranted) {
            throw new AccessDeniedException('No access to this type of parent entities.');
        }
    }

    /**
     * @param SubresourceContext $context
     *
     * @return bool
     */
    private function isGrantedForClass(SubresourceContext $context): bool
    {
        $isGranted = true;

        $className = $context->getManageableParentEntityClass($this->doctrineHelper);
        if ($className) {
            $isGranted = $this->authorizationChecker->isGranted(
                $this->permission,
                ObjectIdentityHelper::encodeIdentityString(
                    EntityAclExtension::NAME,
                    ObjectIdentityHelper::buildType($className, $this->aclGroupProvider->getGroup())
                )
            );
        }

        return $isGranted;
    }
}
