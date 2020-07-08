<?php

namespace Oro\Bundle\SecurityBundle\ORM\Walker;

use Oro\Bundle\SecurityBundle\AccessRule\AccessRuleExecutor;

/**
 * Represents a context in which AccessRuleWalker works in.
 */
class AccessRuleWalkerContext implements \Serializable
{
    /** @var AccessRuleExecutor Not serializable */
    private $accessRuleExecutor;

    /** @var string */
    private $permission;

    /** @var string */
    private $userClass;

    /** @var integer */
    private $userId;

    /** @var integer */
    private $organizationId;

    /** @var array [option name => option value, ...] */
    private $options = [];

    /**
     * @param AccessRuleExecutor $accessRuleExecutor
     * @param string             $permission
     * @param string             $userClass
     * @param null               $userId
     * @param null               $organizationId
     */
    public function __construct(
        AccessRuleExecutor $accessRuleExecutor,
        $permission = 'VIEW',
        $userClass = '',
        $userId = null,
        $organizationId = null
    ) {
        $this->accessRuleExecutor = $accessRuleExecutor;
        $this->permission = $permission;
        $this->userClass = $userClass;
        $this->userId = $userId;
        $this->organizationId = $organizationId;
    }

    /**
     * Gets the access rule executor.
     *
     * @return AccessRuleExecutor
     */
    public function getAccessRuleExecutor(): AccessRuleExecutor
    {
        return $this->accessRuleExecutor;
    }

    /**
     * Returns the permission the object should be checked.
     *
     * @return string
     */
    public function getPermission(): ?string
    {
        return $this->permission;
    }

    /**
     * Returns current logged user class name.
     *
     * @return string
     */
    public function getUserClass(): ?string
    {
        return $this->userClass;
    }

    /**
     * Returns current logged user id.
     *
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Returns organization id.
     *
     * @return int|null
     */
    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    /**
     * Sets organization id.
     *
     * @param int $organizationId
     */
    public function setOrganizationId(int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Returns true if the additional parameter exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasOption(string $key): bool
    {
        return \array_key_exists($key, $this->options);
    }

    /**
     * Gets the given additional option. In case if the option does not exist, the default value is returned.
     *
     * @param string $key
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getOption(string $key, $defaultValue = null)
    {
        if (!\array_key_exists($key, $this->options)) {
            return $defaultValue;
        }

        return $this->options[$key];
    }

    /**
     * Gets all additional options.
     *
     * @return array [option name => option value, ...]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Sets an additional option.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setOption(string $key, $value): void
    {
        $this->options[$key] = $value;
    }

    /**
     * Removes an additional option.
     *
     * @param string $key
     */
    public function removeOption(string $key): void
    {
        unset($this->options[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return \json_encode(
            \array_merge(
                $this->options,
                [
                    'permission'      => $this->permission,
                    'user_class'      => $this->userClass,
                    'user_id'         => $this->userId,
                    'organization_id' => $this->organizationId,
                ]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        throw new \RuntimeException('Not supported');
    }
}
