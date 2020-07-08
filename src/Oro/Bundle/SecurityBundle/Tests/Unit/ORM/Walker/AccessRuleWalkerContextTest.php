<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\ORM\Walker;

use Oro\Bundle\SecurityBundle\AccessRule\AccessRuleExecutor;
use Oro\Bundle\SecurityBundle\ORM\Walker\AccessRuleWalkerContext;
use Oro\Bundle\UserBundle\Entity\User;
use PHPUnit\Framework\TestCase;

class AccessRuleWalkerContextTest extends TestCase
{
    public function testPermissionWithDefaultValue()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class)
        );

        $this->assertEquals('VIEW', $context->getPermission());
    }

    public function testPermission()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT'
        );

        $this->assertEquals('EDIT', $context->getPermission());
    }

    public function testUserClassWithDefaultValue()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT'
        );

        $this->assertEmpty($context->getUserClass());
    }

    public function testUserClass()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class
        );

        $this->assertEquals(User::class, $context->getUserClass());
    }

    public function testUserIdWithDefaultValue()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class
        );

        $this->assertNull($context->getUserId());
    }

    public function testUserId()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class,
            75
        );

        $this->assertEquals(75, $context->getUserId());
    }


    public function testOrganizationIdWithDefaultValue()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class,
            75
        );

        $this->assertNull($context->getOrganizationId());
    }

    public function testOrganizationId()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class,
            75,
            2
        );

        $this->assertEquals(2, $context->getOrganizationId());
    }

    public function testGetAccessRuleExecutor()
    {
        $accessRuleExecutor = $this->createMock(AccessRuleExecutor::class);
        $context = new AccessRuleWalkerContext($accessRuleExecutor);

        $this->assertSame($accessRuleExecutor, $context->getAccessRuleExecutor());
    }

    public function testAdditionalParameters()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class)
        );

        $this->assertFalse($context->hasOption('test'));
        $this->assertNull($context->getOption('test'));
        $this->assertEquals('default', $context->getOption('test', 'default'));

        $context->setOption('test', 'test_value');

        $this->assertTrue($context->hasOption('test'));
        $this->assertEquals('test_value', $context->getOption('test', 'default'));
    }

    public function testSerialize()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class),
            'EDIT',
            User::class,
            75,
            2
        );
        $context->setOption('parameter1', 'value1');
        $context->setOption('parameter2', 'value2');

        $this->assertEquals(
            '{"parameter1":"value1","parameter2":"value2","permission":"EDIT",'
                .'"user_class":"Oro\\\\Bundle\\\\UserBundle\\\\Entity\\\\User","user_id":75,"organization_id":2}',
            $context->serialize()
        );
    }

    public function testUnserialize()
    {
        $this->expectException(\RuntimeException::class);
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class)
        );
        $context->unserialize('');
    }

    public function testGetOptions()
    {
        $context = new AccessRuleWalkerContext(
            $this->createMock(AccessRuleExecutor::class)
        );
        $context->setOption('parameter1', 'value1');
        $context->setOption('parameter2', 'value2');

        $this->assertEquals(
            [
                'parameter1' => 'value1',
                'parameter2' => 'value2'
            ],
            $context->getOptions()
        );
    }
}
