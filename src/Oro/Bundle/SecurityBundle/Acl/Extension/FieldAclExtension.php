<?php

namespace Oro\Bundle\SecurityBundle\Acl\Extension;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\SecurityBundle\Owner\Metadata\MetadataProviderInterface;

class FieldAclExtension extends AbstractSimpleAccessLevelAclExtension
{
    const PERMISSION_VIEW   = 'VIEW';
    const PERMISSION_CREATE = 'CREATE';
    const PERMISSION_EDIT   = 'EDIT';

    /** @var ConfigProvider */
    protected $securityConfigProvider;

    /** @var EntitySecurityMetadataProvider */
    protected $entityMetadataProvider;

    /**
     * @param ObjectIdAccessor                           $objectIdAccessor
     * @param MetadataProviderInterface                  $metadataProvider
     * @param AccessLevelOwnershipDecisionMakerInterface $decisionMaker
     * @param EntityOwnerAccessor                        $entityOwnerAccessor
     * @param ConfigProvider                             $configProvider
     * @param EntitySecurityMetadataProvider             $entityMetadataProvider
     */
    public function __construct(
        ObjectIdAccessor $objectIdAccessor,
        MetadataProviderInterface $metadataProvider,
        AccessLevelOwnershipDecisionMakerInterface $decisionMaker,
        EntityOwnerAccessor $entityOwnerAccessor,
        ConfigProvider $configProvider,
        EntitySecurityMetadataProvider $entityMetadataProvider
    ) {
        parent::__construct($objectIdAccessor, $metadataProvider, $entityOwnerAccessor, $decisionMaker);
        $this->securityConfigProvider = $configProvider;
        $this->entityMetadataProvider = $entityMetadataProvider;

        $this->permissions = [
            self::PERMISSION_VIEW,
            self::PERMISSION_CREATE,
            self::PERMISSION_EDIT,
        ];

        $this->map = [
            self::PERMISSION_VIEW   => [
                FieldMaskBuilder::MASK_VIEW_BASIC,
                FieldMaskBuilder::MASK_VIEW_LOCAL,
                FieldMaskBuilder::MASK_VIEW_DEEP,
                FieldMaskBuilder::MASK_VIEW_GLOBAL,
                FieldMaskBuilder::MASK_VIEW_SYSTEM,
            ],
            self::PERMISSION_CREATE => [
                FieldMaskBuilder::MASK_CREATE_SYSTEM,
            ],
            self::PERMISSION_EDIT   => [
                FieldMaskBuilder::MASK_EDIT_BASIC,
                FieldMaskBuilder::MASK_EDIT_LOCAL,
                FieldMaskBuilder::MASK_EDIT_DEEP,
                FieldMaskBuilder::MASK_EDIT_GLOBAL,
                FieldMaskBuilder::MASK_EDIT_SYSTEM,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getExtensionKey()
    {
        throw new \LogicException('Field ACL Extension does not support "getExtensionKey" method.');
    }

    /**
     * {@inheritdoc}
     */
    public function supports($type, $id)
    {
        throw new \LogicException('Field ACL Extension does not support "supports" method.');
    }

    /**
     * {@inheritdoc}
     */
    public function getClasses()
    {
        throw new \LogicException('Field ACL Extension does not support "getClasses" method.');
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectIdentity($val)
    {
        throw new \LogicException('Field ACL Extension does not support "getObjectIdentity" method.');
    }

    /**
     * {@inheritdoc}
     */
    public function adaptRootMask($rootMask, $object)
    {
        throw new \LogicException('Field ACL Extension does not support "adaptRootMask" method.');
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedPermissions(ObjectIdentity $oid, $fieldName = null)
    {
        $fields = $this->entityMetadataProvider->getMetadata($oid->getType())->getFields();
        $result = $fields[$fieldName]->getPermissions();
        if (empty($result)) {
            $result = $this->permissions;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessLevelNames($object, $permissionName = null)
    {
        if (self::PERMISSION_CREATE === $permissionName) {
            // only system and none access levels are applicable to CREATE permission
            return AccessLevel::getAccessLevelNames(AccessLevel::SYSTEM_LEVEL);
        }

        return parent::getAccessLevelNames($object, $permissionName);
    }

    /**
     * {@inheritdoc}
     */
    public function decideIsGranting($triggeredMask, $object, TokenInterface $securityToken)
    {
        if (!$this->isSupportedObject($object)) {
            return true;
        }

        if (!$this->isFieldLevelAclEnabled($object)) {
            return true;
        }

        return $this->isAccessGranted($triggeredMask, $object, $securityToken);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaskPattern($mask)
    {
        return FieldMaskBuilder::getPatternFor($mask);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaskBuilder($permission)
    {
        return new FieldMaskBuilder();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllMaskBuilders()
    {
        return [new FieldMaskBuilder()];
    }

    /**
     * {@inheritdoc}
     */
    protected function getMaskBuilderConst($constName)
    {
        return FieldMaskBuilder::getConst($constName);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseDescriptor($descriptor, &$type, &$id, &$group)
    {
        $descriptor = ObjectIdentityHelper::removeFieldName($descriptor);

        return parent::parseDescriptor($descriptor, $type, $id, $group);
    }

    /**
     * @param object $object
     *
     * @return bool
     */
    protected function isFieldLevelAclEnabled($object)
    {
        $securityConfig = $this->securityConfigProvider->getConfig($this->getObjectClassName($object));

        return
            $securityConfig->get('field_acl_supported')
            && $securityConfig->get('field_acl_enabled');
    }
}
