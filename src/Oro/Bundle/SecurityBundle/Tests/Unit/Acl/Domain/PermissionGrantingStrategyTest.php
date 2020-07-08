<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain;

use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Domain\PermissionGrantingStrategy;
use Oro\Bundle\SecurityBundle\Acl\Domain\PermissionGrantingStrategyContextInterface;
use Oro\Bundle\SecurityBundle\Acl\Extension\AclExtensionInterface;
use Oro\Bundle\SecurityBundle\Acl\Permission\MaskBuilder;
use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\TestEntity;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Domain\Fixtures\Entity\User;
use Oro\Component\DependencyInjection\ServiceLink;
use Symfony\Component\Security\Acl\Domain\Acl;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\NoAceFoundException;
use Symfony\Component\Security\Acl\Model\AuditLoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PermissionGrantingStrategyTest extends \PHPUnit\Framework\TestCase
{
    const SERVICE_BITS        = -16;
    const REMOVE_SERVICE_BITS = 15;
    const SERVICE_BITS_0      = 0;
    const SERVICE_BITS_1      = 16;
    const MASK_CREATE_BASIC   = 1 + self::SERVICE_BITS_0;
    const MASK_CREATE_SYSTEM  = 2 + self::SERVICE_BITS_0;
    const MASK_DELETE_BASIC   = 4 + self::SERVICE_BITS_0;
    const MASK_DELETE_SYSTEM  = 8 + self::SERVICE_BITS_0;
    const MASK_VIEW_BASIC     = 1 + self::SERVICE_BITS_1;
    const MASK_VIEW_SYSTEM    = 2 + self::SERVICE_BITS_1;
    const MASK_EDIT_BASIC     = 4 + self::SERVICE_BITS_1;
    const MASK_EDIT_SYSTEM    = 8 + self::SERVICE_BITS_1;
    const GROUP_CREATE        = self::MASK_CREATE_BASIC + self::MASK_CREATE_SYSTEM;
    const GROUP_DELETE        = self::MASK_DELETE_BASIC + self::MASK_DELETE_SYSTEM;
    const GROUP_VIEW          = self::MASK_VIEW_BASIC + self::MASK_VIEW_SYSTEM;
    const GROUP_EDIT          = self::MASK_EDIT_BASIC + self::MASK_EDIT_SYSTEM;
    const GROUP_BASIC         = 1 + 4;
    const GROUP_SYSTEM        = 2 + 8;

    /** @var UserSecurityIdentity */
    private $sid;

    /** @var \PHPUnit\Framework\MockObject\MockObject|TokenInterface */
    private $securityToken;

    /** @var \PHPUnit\Framework\MockObject\MockObject|PermissionGrantingStrategyContextInterface */
    private $context;

    /** @var \PHPUnit\Framework\MockObject\MockObject|AclExtensionInterface */
    private $extension;

    /** @var PermissionGrantingStrategy */
    private $strategy;

    protected function setUp(): void
    {
        $this->extension = $this->createMock(AclExtensionInterface::class);
        $this->configureTestAclExtension();

        $this->context = $this->createMock(PermissionGrantingStrategyContextInterface::class);
        $this->context->expects(self::any())
            ->method('getAclExtension')
            ->willReturn($this->extension);

        $user = new User(1);
        $user->setUsername('TestUser');
        $this->sid = new UserSecurityIdentity('TestUser', get_class($user));

        $this->securityToken = $this->createMock(TokenInterface::class);
        $this->context->expects(self::any())
            ->method('getSecurityToken')
            ->willReturn($this->securityToken);

        $this->strategy = new PermissionGrantingStrategy();
        $this->strategy->setContext($this->createServiceLink($this->context));
    }

    /**
     * @param object $service
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|ServiceLink
     */
    private function createServiceLink($service)
    {
        $serviceLink = $this->createMock(ServiceLink::class);
        $serviceLink->expects(self::any())
            ->method('getService')
            ->will($this->returnValue($service));

        return $serviceLink;
    }

    /**
     * @param ObjectIdentity|null $oid
     * @param bool                $entriesInheriting
     *
     * @return Acl
     */
    private function getAcl($oid = null, $entriesInheriting = true)
    {
        static $id = 1;

        if ($oid === null) {
            $oid = new ObjectIdentity($this->context->getObject()->getId(), get_class($this->context->getObject()));
        }

        return new Acl(
            $id++,
            $oid,
            $this->strategy,
            [],
            $entriesInheriting
        );
    }

    private function configureTestAclExtension()
    {
        $this->extension->expects(self::any())
            ->method('getExtensionKey')
            ->willReturn('test_acl_extension');

        $this->extension->expects(self::any())
            ->method('getServiceBits')
            ->willReturnCallback(function ($mask) {
                return $mask & self::SERVICE_BITS;
            });
        $this->extension->expects(self::any())
            ->method('removeServiceBits')
            ->willReturnCallback(function ($mask) {
                return $mask & self::REMOVE_SERVICE_BITS;
            });

        $this->extension->expects(self::any())
            ->method('getAccessLevel')
            ->willReturnCallback(function ($mask, $permission = null, $object = null) {
                if ($mask & self::GROUP_BASIC) {
                    return AccessLevel::BASIC_LEVEL;
                }
                if ($mask & self::GROUP_SYSTEM) {
                    return AccessLevel::SYSTEM_LEVEL;
                }

                return AccessLevel::NONE_LEVEL;
            });

        $this->extension->expects(self::any())
            ->method('getPermissions')
            ->willReturnCallback(function ($mask = null, $setOnly = false, $byCurrentGroup = false) {
                if (null === $mask) {
                    return ['CREATE', 'DELETE', 'VIEW', 'EDIT'];
                }
                $result = [];
                if ($mask & self::SERVICE_BITS_1) {
                    if (!$setOnly) {
                        $result = ['VIEW', 'EDIT'];
                    } else {
                        if ($mask & self::GROUP_VIEW) {
                            $result[] = 'VIEW';
                        }
                        if ($mask & self::GROUP_EDIT) {
                            $result[] = 'EDIT';
                        }
                    }
                } else {
                    if (!$setOnly) {
                        $result = ['CREATE', 'DELETE'];
                    } else {
                        if ($mask & self::GROUP_CREATE) {
                            $result[] = 'CREATE';
                        }
                        if ($mask & self::GROUP_DELETE) {
                            $result[] = 'DELETE';
                        }
                    }
                }

                return $result;
            });

        $this->extension->expects(self::any())
            ->method('adaptRootMask')
            ->willReturnCallback(function ($rootMask, $object) {
                return $rootMask;
            });

        $this->extension->expects(self::any())
            ->method('getMaskBuilder')
            ->willReturnCallback(function ($permission) {
                $maskBuilder = $this->createMock(MaskBuilder::class);
                $maskBuilder->expects(self::any())
                    ->method('hasMaskForGroup')
                    ->willReturnCallback(function ($name) {
                        return in_array($name, ['CREATE', 'DELETE', 'VIEW', 'EDIT'], true);
                    });
                $maskBuilder->expects(self::any())
                    ->method('getMaskForGroup')
                    ->willReturnCallback(function ($name) {
                        return constant(PermissionGrantingStrategyTest::class . '::GROUP_' . $name);
                    });

                return $maskBuilder;
            });
    }

    /**
     * @param mixed $object
     */
    private function setObjectToContext($object)
    {
        $this->context->expects(self::any())
            ->method('getObject')
            ->willReturn($object);
    }

    public function testGetContext()
    {
        $this->assertSame($this->context, $this->strategy->getContext());
    }

    public function testIsGrantedReturnsExceptionIfNoAceIsFound()
    {
        $this->expectException(\Symfony\Component\Security\Acl\Exception\NoAceFoundException::class);
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid]);
    }

    public function testIsGrantedObjectAcesHavePriority()
    {
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertClassAce($this->sid, self::MASK_VIEW_BASIC);
        $acl->insertObjectAce($this->sid, self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertFalse(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    public function testIsGrantedUsesClassAcesIfNoApplicableObjectAceWasFound()
    {
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertClassAce($this->sid, self::MASK_VIEW_BASIC);
        $acl->insertObjectAce(new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    public function testIsGrantedPrefersLocalAcesOverParentAclAces()
    {
        $this->setObjectToContext(new TestEntity(123));

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassAce($this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl();
        $acl->setParentAcl($parentAcl);
        $acl->insertClassAce($this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    public function testIsGrantedUsesParentAcesIfNoLocalAcesAreApplicable()
    {
        $this->setObjectToContext(new TestEntity(123));

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassAce($this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl();
        $acl->setParentAcl($parentAcl);
        $acl->insertClassAce(new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_SYSTEM)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    public function testIsGrantedUsesParentAcesOnlyIfInheritingIsSet()
    {
        $this->expectException(\Symfony\Component\Security\Acl\Exception\NoAceFoundException::class);
        $this->setObjectToContext(new TestEntity(123));

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassAce($this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl(null, false);
        $acl->setParentAcl($parentAcl);
        $acl->insertClassAce(new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC);

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid]);
    }

    public function testIsGrantedAllowAccessIfThereIsGrantedAceForAtLeastOneSidTestWhenFirstSidGranted()
    {
        $this->setObjectToContext(new TestEntity(123));

        $anotherSid = new RoleSecurityIdentity('ROLE_USER');

        $acl = $this->getAcl();
        $acl->insertClassAce($anotherSid, self::MASK_VIEW_BASIC, 0, false);
        $acl->insertClassAce($this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::exactly(2))
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted(
                $acl,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid, $anotherSid]
            )
        );
    }

    public function testIsGrantedAllowAccessIfThereIsGrantedAceForAtLeastOneSidTestWhenSecondSidGranted()
    {
        $this->setObjectToContext(new TestEntity(123));

        $anotherSid = new RoleSecurityIdentity('ROLE_USER');

        $acl = $this->getAcl();
        $acl->insertClassAce($anotherSid, self::MASK_VIEW_BASIC);
        $acl->insertClassAce($this->sid, self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::exactly(2))
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted(
                $acl,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid, $anotherSid]
            )
        );
    }

    public function testIsGrantedCallsAuditLoggerOnGrant()
    {
        $this->setObjectToContext(new TestEntity(123));

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects(self::once())
            ->method('logIfNeeded')
            ->with(true);
        $this->strategy->setAuditLogger($logger);

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, self::MASK_VIEW_BASIC);
        $acl->updateObjectAuditing(0, true, false);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    public function testIsGrantedCallsAuditLoggerOnDeny()
    {
        $this->setObjectToContext(new TestEntity(123));

        $logger = $this->createMock(AuditLoggerInterface::class);
        $logger->expects($this->once())
            ->method('logIfNeeded')
            ->with(false);
        $this->strategy->setAuditLogger($logger);

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, self::MASK_VIEW_BASIC, 0, false);
        $acl->updateObjectAuditing(0, false, true);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertFalse(
            $this->strategy->isGranted($acl, [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM], [$this->sid])
        );
    }

    /**
     * @dataProvider grantingStrategyProvider
     */
    public function testIsGrantedStrategies($strategy, $mask, $requiredMask, $result, $noAceFoundException)
    {
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, $mask, 0, true, $strategy);

        if ($result) {
            $this->extension->expects(self::once())
                ->method('decideIsGranting')
                ->with($requiredMask)
                ->willReturn(true);
        } else {
            $this->extension->expects(self::never())
                ->method('decideIsGranting');
        }

        $this->assertIsGranted($acl, $requiredMask, $result, $noAceFoundException);
    }

    /**
     * @dataProvider permissionGrantingStrategyProvider
     */
    public function testIsGrantedPermissionStrategy($strategy, $mask, $requiredMask, $result, $noAceFoundException)
    {
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, $mask, 0, true, $strategy);

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        $this->assertIsGranted($acl, $requiredMask, $result, $noAceFoundException);
    }

    /**
     * @dataProvider grantingStrategyProvider
     */
    private function assertIsGranted(Acl $acl, $requiredMask, $result, $noAceFoundException)
    {
        if (false === $result && !$noAceFoundException) {
            if ($noAceFoundException) {
                $this->expectException(NoAceFoundException::class);
                $this->strategy->isGranted($acl, [$requiredMask], [$this->sid]);
            } else {
                try {
                    $this->strategy->isGranted($acl, [$requiredMask], [$this->sid]);
                    $this->fail('The ACE is not supposed to match.');
                } catch (NoAceFoundException $noAce) {
                }
            }
        } else {
            $this->extension->expects(self::any())
                ->method('getPermissionGroupMask')
                ->willReturnCallback(function ($mask) {
                    if ($mask & self::GROUP_VIEW) {
                        return self::GROUP_VIEW;
                    }
                    if ($mask & self::GROUP_EDIT) {
                        return self::GROUP_EDIT;
                    }
                    if ($mask & self::GROUP_CREATE) {
                        return self::GROUP_CREATE;
                    }
                    if ($mask & self::GROUP_DELETE) {
                        return self::GROUP_DELETE;
                    }

                    return null;
                });
            $this->assertSame(
                $result,
                $this->strategy->isGranted($acl, [$requiredMask], [$this->sid])
            );
        }
    }

    /**
     * @return array
     */
    public function grantingStrategyProvider()
    {
        return [
            'ALL: mask contains requiredMask'                                                => [
                'strategy'            => 'all',
                'mask'                => self::MASK_VIEW_BASIC | self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => true,
                'noAceFoundException' => false,
            ],
            'ANY: mask contains requiredMask'                                                => [
                'strategy'            => 'any',
                'mask'                => self::MASK_VIEW_BASIC | self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => true,
                'noAceFoundException' => false,
            ],
            'EQUAL: mask contains requiredMask'                                              => [
                'strategy'            => 'equal',
                'mask'                => self::MASK_VIEW_BASIC | self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
            'ALL: mask does not contains requiredMask'                                       => [
                'strategy'            => 'all',
                'mask'                => self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => true,
            ],
            'ANY: mask does not contains requiredMask'                                       => [
                'strategy'            => 'any',
                'mask'                => self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => true,
            ],
            'EQUAL: mask does not contains requiredMask'                                     => [
                'strategy'            => 'equal',
                'mask'                => self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => true,
            ],
            'ALL: mask and requiredMask have same access bits, but different service bits'   => [
                'strategy'            => 'all',
                'mask'                => self::MASK_CREATE_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
            'ANY: mask and requiredMask have same access bits, but different service bits'   => [
                'strategy'            => 'any',
                'mask'                => self::MASK_CREATE_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
            'EQUAL: mask and requiredMask have same access bits, but different service bits' => [
                'strategy'            => 'equal',
                'mask'                => self::MASK_CREATE_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
        ];
    }

    /**
     * @return array
     */
    public function permissionGrantingStrategyProvider()
    {
        return [
            'ALL: mask contains requiredMask'                                              => [
                'strategy'            => 'perm',
                'mask'                => self::MASK_VIEW_BASIC | self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => true,
                'noAceFoundException' => false,
            ],
            'ALL: mask does not contains requiredMask'                                     => [
                'strategy'            => 'perm',
                'mask'                => self::MASK_EDIT_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
            'ALL: mask and requiredMask have same access bits, but different service bits' => [
                'strategy'            => 'perm',
                'mask'                => self::MASK_CREATE_BASIC,
                'requiredMask'        => self::MASK_VIEW_BASIC,
                'result'              => false,
                'noAceFoundException' => false,
            ],
        ];
    }

    public function testIsGrantedForPermissionStrategyShouldCheckOnlyPermissionEncodedInAceMask()
    {
        $this->expectException(\Symfony\Component\Security\Acl\Exception\NoAceFoundException::class);
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, self::MASK_VIEW_BASIC, 0, false, 'perm');

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        $this->strategy->isGranted(
            $acl,
            [self::MASK_EDIT_BASIC, self::MASK_EDIT_SYSTEM],
            [$this->sid]
        );
    }

    public function testIsGrantedShouldConsiderZeroPermissionMaskAsDeniedForNotPermissionStrategy()
    {
        $this->setObjectToContext(new TestEntity(123));

        $acl = $this->getAcl();
        $acl->insertObjectAce($this->sid, self::MASK_VIEW_BASIC, 0, false, 'all');

        $this->extension->expects(self::exactly(2))
            ->method('getPermissionGroupMask')
            ->willReturnMap([
                [self::MASK_VIEW_BASIC, self::GROUP_VIEW],
                [self::MASK_EDIT_SYSTEM, self::GROUP_EDIT]
            ]);
        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        $this->assertFalse(
            $this->strategy->isGranted(
                $acl,
                [self::MASK_EDIT_BASIC, self::MASK_EDIT_SYSTEM],
                [$this->sid]
            )
        );
    }

    /**
     * @param object      $object
     * @param string      $fieldName
     * @param string|null $fieldAlias
     */
    private function setFieldSecurityMetadata($object, $fieldName, $fieldAlias = null)
    {
        $securityMetadataProvider = $this->createMock(EntitySecurityMetadataProvider::class);
        $securityMetadataProvider->expects($this->any())
            ->method('isProtectedEntity')
            ->willReturn(true);
        $securityMetadataProvider->expects($this->any())
            ->method('getProtectedFieldName')
            ->with(get_class($object))
            ->willReturnCallback(function ($cls, $fld) use ($fieldName, $fieldAlias) {
                return $fld === $fieldName && $fieldAlias
                    ? $fieldAlias
                    : $fieldName;
            });

        $this->strategy->setSecurityMetadataProvider($this->createServiceLink($securityMetadataProvider));
    }

    public function testIsFieldGrantedReturnsTrueIfNoAceIsFound()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $acl = $this->getAcl();

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        self::assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedObjectAcesHavePriority()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC);
        $acl->insertObjectFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertFalse(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedUsesClassAcesIfNoApplicableObjectAceWasFound()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC);
        $acl->insertObjectFieldAce($fieldName, new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedPrefersLocalAcesOverParentAclAces()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl();
        $acl->setParentAcl($parentAcl);
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedUsesParentAcesIfNoLocalAcesAreApplicable()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl();
        $acl->setParentAcl($parentAcl);
        $acl->insertClassFieldAce($fieldName, new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_SYSTEM)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedUsesParentAcesOnlyIfInheritingIsSet()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $parentAcl = $this->getAcl();
        $parentAcl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_SYSTEM);

        $acl = $this->getAcl(null, false);
        $acl->setParentAcl($parentAcl);
        $acl->insertClassFieldAce($fieldName, new RoleSecurityIdentity('ROLE_USER'), self::MASK_VIEW_BASIC);

        $this->extension->expects(self::never())
            ->method('decideIsGranting');

        self::assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedAllowAccessIfThereIsGrantedAceForAtLeastOneSidTestWhenFirstSidGranted()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $anotherSid = new RoleSecurityIdentity('ROLE_USER');

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldName, $anotherSid, self::MASK_VIEW_BASIC, 0, false);
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::exactly(2))
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid, $anotherSid]
            )
        );
    }

    public function testIsFieldGrantedAllowAccessIfThereIsGrantedAceForAtLeastOneSidTestWhenSecondSidGranted()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $anotherSid = new RoleSecurityIdentity('ROLE_USER');

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldName, $anotherSid, self::MASK_VIEW_BASIC);
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC, 0, false);

        $this->extension->expects(self::exactly(2))
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid, $anotherSid]
            )
        );
    }

    public function testIsFieldGrantedForFieldWithAlias()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $fieldAlias = 'fieldAlias';
        $this->setFieldSecurityMetadata($obj, $fieldName, $fieldAlias);

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldAlias, $this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsFieldGrantedForFieldWithoutAlias()
    {
        $obj = new TestEntity(123);
        $this->setObjectToContext($obj);
        $fieldName = 'field';
        $this->setFieldSecurityMetadata($obj, $fieldName);

        $acl = $this->getAcl();
        $acl->insertClassFieldAce($fieldName, $this->sid, self::MASK_VIEW_BASIC);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_VIEW_BASIC)
            ->willReturn(true);

        $this->assertTrue(
            $this->strategy->isFieldGranted(
                $acl,
                $fieldName,
                [self::MASK_VIEW_BASIC, self::MASK_VIEW_SYSTEM],
                [$this->sid]
            )
        );
    }

    public function testIsGrantedInCaseOfTwoRolesShouldReturnCorrectAccessLevel()
    {
        $object = new TestEntity(123);
        $this->setObjectToContext($object);

        $acl = $this->getAcl();

        $sidRole1 = new RoleSecurityIdentity('ROLE_USER1');
        $sidRole2 = new RoleSecurityIdentity('ROLE_USER2');

        $acl->insertObjectAce($sidRole1, 0, 0);
        $acl->insertObjectAce($sidRole2, self::MASK_CREATE_BASIC, 1);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_CREATE_BASIC)
            ->willReturn(true);

        $this->extension->expects($this->any())
            ->method('getAccessLevel')
            ->willReturnMap([
                [self::MASK_CREATE_BASIC, null, $object, AccessLevel::BASIC_LEVEL],
                [self::MASK_CREATE_SYSTEM, null, $object, AccessLevel::SYSTEM_LEVEL],
            ]);

        // The Basic access level should be set to context.
        $this->context->expects($this->once())
            ->method('setTriggeredMask')
            ->with(self::MASK_CREATE_BASIC, AccessLevel::BASIC_LEVEL);

        $isGranted = $this->strategy->isGranted(
            $acl,
            [self::MASK_CREATE_BASIC, self::MASK_CREATE_SYSTEM],
            [$sidRole1, $sidRole2]
        );

        $this->assertTrue($isGranted);
    }

    public function testIsGrantedInCaseOfTwoRolesShouldReturnCorrectAccessLevelForField()
    {
        $field = 'testField';
        $object = new TestEntity(123);
        $this->setObjectToContext($object);
        $this->setFieldSecurityMetadata($object, $field);

        $acl = $this->getAcl();

        $sidRole1 = new RoleSecurityIdentity('ROLE_USER1');
        $sidRole2 = new RoleSecurityIdentity('ROLE_USER2');

        $acl->insertObjectFieldAce($field, $sidRole1, 0, 0);
        $acl->insertObjectFieldAce($field, $sidRole2, self::MASK_CREATE_BASIC, 1);

        $this->extension->expects(self::once())
            ->method('decideIsGranting')
            ->with(self::MASK_CREATE_BASIC)
            ->willReturn(true);

        $this->extension->expects($this->any())
            ->method('getAccessLevel')
            ->willReturnMap([
                [self::MASK_CREATE_BASIC, null, $object, AccessLevel::BASIC_LEVEL],
                [self::MASK_CREATE_SYSTEM, null, $object, AccessLevel::SYSTEM_LEVEL],
            ]);

        // The Basic access level should be set to context.
        $this->context->expects($this->once())
            ->method('setTriggeredMask')
            ->with(self::MASK_CREATE_BASIC, AccessLevel::BASIC_LEVEL);

        $isGranted = $this->strategy->isFieldGranted(
            $acl,
            $field,
            [self::MASK_CREATE_BASIC, self::MASK_CREATE_SYSTEM],
            [$sidRole1, $sidRole2]
        );

        $this->assertTrue($isGranted);
    }
}
