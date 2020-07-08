<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Acl\Extension;

use Oro\Bundle\SecurityBundle\Acl\Domain\DomainObjectReference;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Acl\Extension\AccessLevelOwnershipDecisionMakerInterface;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProviderInterface;
use Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Extension\Stub\DomainObjectStub;
use Oro\Bundle\WorkflowBundle\Acl\Extension\WorkflowAclExtension;
use Oro\Bundle\WorkflowBundle\Acl\Extension\WorkflowTransitionAclExtension;
use Oro\Bundle\WorkflowBundle\Acl\Extension\WorkflowTransitionMaskBuilder;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionManager;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Oro\Bundle\WorkflowBundle\Resolver\TransitionOptionsResolver;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class WorkflowTransitionAclExtensionTest extends \PHPUnit\Framework\TestCase
{
    private const PATTERN_ALL_OFF = '(P) system:. global:. deep:. local:. basic:.';

    /** @var ObjectIdAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $objectIdAccessor;

    /** @var OwnershipMetadataProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $metadataProvider;

    /** @var EntityOwnerAccessor|\PHPUnit\Framework\MockObject\MockObject */
    private $entityOwnerAccessor;

    /** @var AccessLevelOwnershipDecisionMakerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $decisionMaker;

    /** @var WorkflowManager|\PHPUnit\Framework\MockObject\MockObject */
    private $workflowManager;

    /** @var \PHPUnit\Framework\MockObject\MockObject|TransitionOptionsResolver */
    private $optionsResolver;

    /** @var WorkflowTransitionAclExtension */
    private $extension;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->objectIdAccessor = $this->createMock(ObjectIdAccessor::class);
        $this->metadataProvider = $this->createMock(OwnershipMetadataProviderInterface::class);
        $this->entityOwnerAccessor = $this->createMock(EntityOwnerAccessor::class);
        $this->decisionMaker = $this->createMock(AccessLevelOwnershipDecisionMakerInterface::class);
        $this->workflowManager = $this->createMock(WorkflowManager::class);
        $this->optionsResolver = $this->createMock(TransitionOptionsResolver::class);

        $this->extension = new WorkflowTransitionAclExtension(
            $this->objectIdAccessor,
            $this->metadataProvider,
            $this->entityOwnerAccessor,
            $this->decisionMaker,
            $this->workflowManager
        );
    }

    /**
     * @return Transition
     */
    private function createStartTransition()
    {
        $transition = new Transition($this->optionsResolver);
        $transition->setStart(true);

        return $transition;
    }

    public function testGetExtensionKey()
    {
        $this->assertEquals(WorkflowAclExtension::NAME, $this->extension->getExtensionKey());
    }

    public function testSupports()
    {
        self::assertTrue($this->extension->supports('', ''));
    }

    public function testGetClasses()
    {
        $this->expectException(\LogicException::class);
        $this->extension->getClasses();
    }

    public function testGetObjectIdentity()
    {
        $this->expectException(\LogicException::class);
        $this->extension->getObjectIdentity('');
    }

    public function testGetDefaultPermission()
    {
        self::assertSame('', $this->extension->getDefaultPermission());
    }

    /**
     * @dataProvider getPermissionGroupMaskProvider
     */
    public function testGetPermissionGroupMask(int $mask, ?int $expectedPermissionGroupMask)
    {
        self::assertSame($expectedPermissionGroupMask, $this->extension->getPermissionGroupMask($mask));
    }

    public function getPermissionGroupMaskProvider()
    {
        return [
            [0, null],
            [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_BASIC,
                WorkflowTransitionMaskBuilder::GROUP_PERFORM_TRANSITION
            ],
            [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_LOCAL,
                WorkflowTransitionMaskBuilder::GROUP_PERFORM_TRANSITION
            ],
            [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_DEEP,
                WorkflowTransitionMaskBuilder::GROUP_PERFORM_TRANSITION
            ],
            [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_GLOBAL,
                WorkflowTransitionMaskBuilder::GROUP_PERFORM_TRANSITION
            ],
            [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM,
                WorkflowTransitionMaskBuilder::GROUP_PERFORM_TRANSITION
            ]
        ];
    }

    public function testGetAllowedPermissions()
    {
        self::assertEquals(
            ['PERFORM_TRANSITION'],
            $this->extension->getAllowedPermissions(new ObjectIdentity('1', 'test'))
        );
    }

    public function testGetMaskPattern()
    {
        self::assertEquals(self::PATTERN_ALL_OFF, $this->extension->getMaskPattern(0));
    }

    public function testGetMaskBuilder()
    {
        self::assertInstanceOf(
            WorkflowTransitionMaskBuilder::class,
            $this->extension->getMaskBuilder('PERFORM_TRANSITION')
        );
    }

    public function testGetAllMaskBuilders()
    {
        $maskBuilders = $this->extension->getAllMaskBuilders();
        self::assertCount(1, $maskBuilders);
        self::assertInstanceOf(
            WorkflowTransitionMaskBuilder::class,
            $maskBuilders[0]
        );
    }

    /**
     * @dataProvider getServiceBitsProvider
     */
    public function testGetServiceBits($mask, $expectedMask)
    {
        self::assertEquals($expectedMask, $this->extension->getServiceBits($mask));
    }

    /**
     * @return array
     */
    public function getServiceBitsProvider()
    {
        return [
            'zero mask'                        => [
                WorkflowTransitionMaskBuilder::GROUP_NONE,
                WorkflowTransitionMaskBuilder::GROUP_NONE
            ],
            'not zero mask'                    => [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM,
                WorkflowTransitionMaskBuilder::GROUP_NONE
            ],
            'zero mask, not zero identity'     => [
                WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1,
                WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1
            ],
            'not zero mask, not zero identity' => [
                (WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1)
                | WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM,
                WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1
            ],
        ];
    }

    /**
     * @dataProvider removeServiceBitsProvider
     */
    public function testRemoveServiceBits($mask, $expectedMask)
    {
        self::assertEquals($expectedMask, $this->extension->removeServiceBits($mask));
    }

    /**
     * @return array
     */
    public function removeServiceBitsProvider()
    {
        return [
            'zero mask'                        => [
                WorkflowTransitionMaskBuilder::GROUP_NONE,
                WorkflowTransitionMaskBuilder::GROUP_NONE
            ],
            'not zero mask'                    => [
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM,
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM
            ],
            'zero mask, not zero identity'     => [
                WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1,
                WorkflowTransitionMaskBuilder::GROUP_NONE
            ],
            'not zero mask, not zero identity' => [
                (WorkflowTransitionMaskBuilder::REMOVE_SERVICE_BITS + 1)
                | WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM,
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_SYSTEM
            ],
        ];
    }

    /**
     * @dataProvider notSupportedObjectProvider
     */
    public function testDecideIsGrantingForNotSupportedObject($object)
    {
        $securityToken = $this->createMock(TokenInterface::class);
        self::assertTrue($this->extension->decideIsGranting(0, $object, $securityToken));
    }

    /**
     * @return array
     */
    public function notSupportedObjectProvider()
    {
        return [
            ['test'],
            [new ObjectIdentity('1', 'test')]
        ];
    }

    public function testDecideIsGrantingForDomainObjectReference()
    {
        $object = new DomainObjectReference('workflow1', 123, 1, 2);
        $relatedEntity = new \stdClass();
        $securityToken = $this->createMock(TokenInterface::class);

        $workflow = $this->createMock(Workflow::class);
        $workflowDefinition = $this->createMock(WorkflowDefinition::class);
        $workflow->expects(self::once())
            ->method('getDefinition')
            ->willReturn($workflowDefinition);
        $workflowDefinition->expects(self::once())
            ->method('getRelatedEntity')
            ->willReturn($relatedEntity);

        $this->metadataProvider->expects(self::once())
            ->method('getMetadata')
            ->with(self::identicalTo($relatedEntity))
            ->willReturn(new OwnershipMetadata());
        $this->workflowManager->expects(self::once())
            ->method('getWorkflow')
            ->willReturn($workflow);

        self::assertTrue(
            $this->extension->decideIsGranting(0, $object, $securityToken)
        );
    }

    public function testDecideIsGrantingForNoOwningObject()
    {
        $object = new DomainObjectStub();
        $securityToken = $this->createMock(TokenInterface::class);

        $this->metadataProvider->expects(self::once())
            ->method('getMetadata')
            ->with(get_class($object))
            ->willReturn(new OwnershipMetadata());

        self::assertTrue(
            $this->extension->decideIsGranting(0, $object, $securityToken)
        );
    }

    public function testDecideIsGrantingForUserOwningObject()
    {
        $object = new DomainObjectStub();
        $securityToken = $this->createMock(TokenInterface::class);

        $this->metadataProvider->expects(self::once())
            ->method('getMetadata')
            ->with(get_class($object))
            ->willReturn(new OwnershipMetadata('USER', 'owner', 'owner', 'org', 'org'));
        $this->decisionMaker->expects(self::once())
            ->method('isAssociatedWithUser')
            ->willReturn(true);

        self::assertTrue(
            $this->extension->decideIsGranting(
                WorkflowTransitionMaskBuilder::MASK_PERFORM_TRANSITION_BASIC,
                $object,
                $securityToken
            )
        );
    }

    public function testGetAccessLevelNamesWithNonStartTransition()
    {
        $object = 'workflow:test_flow::trans1|step1|step2';

        $workflow = $this->createMock(Workflow::class);

        $definition = new WorkflowDefinition();
        $definition->setRelatedEntity('\stdClass');

        $workflow->expects($this->once())
            ->method('getDefinition')
            ->willReturn($definition);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflow')
            ->with('test_flow')
            ->willReturn($workflow);

        $this->metadataProvider->expects($this->once())
            ->method('getMetadata')
            ->with('\stdClass')
            ->willReturn(new OwnershipMetadata('user', 'user', 'user'));


        $result = $this->extension->getAccessLevelNames($object);

        $this->assertEquals(
            ['NONE', 'BASIC', 'LOCAL', 'DEEP', 'GLOBAL'],
            $result
        );
    }

    public function testGetAccessLevelNamesWithStartTransitionWithoutInitOptions()
    {
        $object = 'workflow:test_flow::trans1||step2';

        $workflow = $this->createMock(Workflow::class);

        $definition = new WorkflowDefinition();
        $definition->setRelatedEntity('\stdClass');

        $workflow->expects($this->once())
            ->method('getDefinition')
            ->willReturn($definition);

        $transitionManager = $this->createMock(TransitionManager::class);

        $transitionManager->expects($this->once())
            ->method('getTransition')
            ->willReturn($this->createStartTransition());

        $workflow->expects($this->once())
            ->method('getTransitionManager')
            ->willReturn($transitionManager);

        $this->workflowManager->expects($this->exactly(2))
            ->method('getWorkflow')
            ->with('test_flow')
            ->willReturn($workflow);

        $this->metadataProvider->expects($this->once())
            ->method('getMetadata')
            ->with('\stdClass')
            ->willReturn(new OwnershipMetadata('user', 'user', 'user'));


        $result = $this->extension->getAccessLevelNames($object);

        $this->assertEquals(
            ['NONE', 'BASIC', 'LOCAL', 'DEEP', 'GLOBAL'],
            $result
        );
    }

    public function testGetAccessLevelNamesWithStartTransitionWithInitEntitiesInitOptions()
    {
        $object = 'workflow:test_flow::trans1||step2';
        $transition = $this->createStartTransition()->setInitEntities(['\Acme\DemoBundle\Entity\TestEntity']);

        $workflow = $this->createMock(Workflow::class);

        $workflow->expects($this->never())
            ->method('getDefinition');

        $transitionManager = $this->createMock(TransitionManager::class);

        $transitionManager->expects($this->once())
            ->method('getTransition')
            ->willReturn($transition);

        $workflow->expects($this->once())
            ->method('getTransitionManager')
            ->willReturn($transitionManager);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflow')
            ->with('test_flow')
            ->willReturn($workflow);

        $this->metadataProvider->expects($this->once())
            ->method('getMetadata')
            ->with(null)
            ->willReturn(new OwnershipMetadata());


        $result = $this->extension->getAccessLevelNames($object);

        $this->assertEquals(
            [0 => 'NONE', 5 => 'SYSTEM'],
            $result
        );
    }

    public function testGetAccessLevelNamesWithStartTransitionWithInitRoutesInitOptions()
    {
        $object = 'workflow:test_flow::trans1||step2';
        $transition = $this->createStartTransition()->setInitRoutes(['some_route']);

        $workflow = $this->createMock(Workflow::class);

        $workflow->expects($this->never())
            ->method('getDefinition');

        $transitionManager = $this->createMock(TransitionManager::class);

        $transitionManager->expects($this->once())
            ->method('getTransition')
            ->willReturn($transition);

        $workflow->expects($this->once())
            ->method('getTransitionManager')
            ->willReturn($transitionManager);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflow')
            ->with('test_flow')
            ->willReturn($workflow);

        $this->metadataProvider->expects($this->once())
            ->method('getMetadata')
            ->with(null)
            ->willReturn(new OwnershipMetadata());


        $result = $this->extension->getAccessLevelNames($object);

        $this->assertEquals(
            [0 => 'NONE', 5 => 'SYSTEM'],
            $result
        );
    }
}
