<?php

namespace Oro\Bundle\SecurityBundle\Acl\Permission;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Configuration\PermissionConfiguration;
use Oro\Bundle\SecurityBundle\Entity\Permission;
use Oro\Bundle\SecurityBundle\Entity\Repository\PermissionRepository;

/**
 * The manager for security permissions.
 */
class PermissionManager
{
    const CACHE_PERMISSIONS = 'permissions';
    const CACHE_GROUPS = 'groups';

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var CacheProvider */
    protected $cache;

    /** @var array */
    protected $groups;

    /** @var array */
    protected $permissions;

    /** @var Permission[] */
    protected $loadedPermissions = [];

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param CacheProvider $cache
     */
    public function __construct(DoctrineHelper $doctrineHelper, CacheProvider $cache)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->cache = $cache;
    }

    /**
     * @param Permission[]|Collection $permissions
     * @return Permission[]|Collection
     */
    public function processPermissions(Collection $permissions)
    {
        $entityRepository = $this->getRepository();
        $entityManager = $this->getEntityManager();
        $processedPermissions = new ArrayCollection();

        foreach ($permissions as $permission) {
            /** @var Permission $existingPermission */
            $existingPermission = $entityRepository->findOneBy(['name' => $permission->getName()]);

            // permission in DB should be overridden if permission with such name already exists
            if ($existingPermission) {
                $existingPermission->import($permission);
                $permission = $existingPermission;
            }

            $entityManager->persist($permission);
            $processedPermissions->add($permission);
        }

        $entityManager->flush();

        $this->buildCache();

        return $processedPermissions;
    }

    /**
     * @param string|null $groupName
     * @return array
     */
    public function getPermissionsMap($groupName = null)
    {
        $this->normalizeGroupName($groupName);

        return $groupName ? $this->findGroupPermissions($groupName) : $this->findPermissions();
    }

    /**
     * @param mixed $entity
     * @param string|null $groupName
     * @return Permission[]
     */
    public function getPermissionsForEntity($entity, $groupName = null)
    {
        $this->normalizeGroupName($groupName);

        $ids = $groupName ? $this->findGroupPermissions($groupName) : null;

        return $this->getRepository()->findByEntityClassAndIds($this->doctrineHelper->getEntityClass($entity), $ids);
    }

    /**
     * @param string $groupName
     * @return Permission[]
     */
    public function getPermissionsForGroup($groupName)
    {
        $this->normalizeGroupName($groupName);

        $ids = $this->findGroupPermissions($groupName);

        return $ids ? $this->getRepository()->findBy(['id' => $ids], ['id' => 'ASC']) : [];
    }

    /**
     * @param string $name
     * @return Permission|null
     */
    public function getPermissionByName($name)
    {
        if (!array_key_exists($name, $this->loadedPermissions)) {
            $map = $this->getPermissionsMap();
            if (isset($map[$name])) {
                $this->loadedPermissions[$name] = $this->getEntityManager()
                    ->getReference(Permission::class, $map[$name]);
            } else {
                $this->loadedPermissions[$name] = null;
            }
        }

        return $this->loadedPermissions[$name];
    }

    /**
     * @return array
     */
    protected function buildCache()
    {
        /** @var Permission[] $permissions */
        $permissions = $this->getRepository()->findBy([], ['id' => 'ASC']);

        $cache = [
            static::CACHE_GROUPS => [],
            static::CACHE_PERMISSIONS => [],
        ];

        foreach ($permissions as $permission) {
            $cache[static::CACHE_PERMISSIONS][$permission->getName()] = $permission->getId();

            foreach ($permission->getGroupNames() as $group) {
                $cache[static::CACHE_GROUPS][$group][$permission->getName()] = $permission->getId();
            }
        }

        $this->cache->deleteAll();
        foreach ($cache as $key => $value) {
            $this->cache->save($key, $value);
        }

        return $cache;
    }

    /**
     * @return array
     */
    protected function findPermissions()
    {
        if (null === $this->permissions) {
            $this->permissions = $this->getCache(static::CACHE_PERMISSIONS);
        }

        return $this->permissions;
    }

    /**
     * @param string $name
     * @return array
     */
    protected function findGroupPermissions($name)
    {
        if (null === $this->groups) {
            $this->groups = $this->getCache(static::CACHE_GROUPS);
        }

        return array_key_exists($name, $this->groups) ? $this->groups[$name] : [];
    }

    /**
     * @param string $key
     * @return array
     */
    protected function getCache($key)
    {
        if (false === ($cache = $this->cache->fetch($key))) {
            $data = $this->buildCache();

            return !empty($data[$key]) ? $data[$key] : [];
        }

        return $cache;
    }

    /**
     * @param string|null $groupName
     */
    protected function normalizeGroupName(&$groupName)
    {
        if ($groupName !== null && empty($groupName)) {
            $groupName = PermissionConfiguration::DEFAULT_GROUP_NAME;
        }
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->doctrineHelper->getEntityManagerForClass(Permission::class);
    }

    /**
     * @return PermissionRepository
     */
    protected function getRepository()
    {
        return $this->doctrineHelper->getEntityRepositoryForClass(Permission::class);
    }
}
