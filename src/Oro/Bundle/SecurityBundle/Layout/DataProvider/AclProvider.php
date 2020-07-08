<?php

namespace Oro\Bundle\SecurityBundle\Layout\DataProvider;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Acl\Util\ClassUtils;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Layout ACL provider Class
 */
class AclProvider
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /** @var ManagerRegistry */
    protected $doctrine;

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param ManagerRegistry               $doctrine
     */
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        ManagerRegistry $doctrine
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->doctrine = $doctrine;
    }

    /**
     * @param string|string[] $attributes
     * @param mixed           $object
     * @return bool
     */
    public function isGranted($attributes, $object = null): bool
    {
        if (is_object($object)) {
            $class = ClassUtils::getRealClass($object);
            $objectManager = $this->doctrine->getManagerForClass($class);
            if ($objectManager instanceof EntityManager) {
                $unitOfWork = $objectManager->getUnitOfWork();
                if ($unitOfWork->isScheduledForInsert($object) || !$unitOfWork->isInIdentityMap($object)) {
                    $object = 'entity:'.$class;
                }
            }
        }

        return $this->authorizationChecker->isGranted($attributes, $object);
    }
}
