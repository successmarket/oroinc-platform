<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowEntityAclIdentityRepository;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowEntityAclIdentity;
use Oro\Bundle\WorkflowBundle\Tests\Functional\DataFixtures\LoadWorkflowEntityAclIdentities;

class WorkflowEntityAclIdentityRepositoryTest extends WebTestCase
{
    /** @var WorkflowEntityAclIdentityRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadWorkflowEntityAclIdentities::class]);

        $this->repository = $this->getContainer()
            ->get('doctrine')
            ->getManagerForClass(WorkflowEntityAclIdentity::class)
            ->getRepository(WorkflowEntityAclIdentity::class);
    }

    public function testFindByClassAndIdentifierAndActiveWorkflows(): void
    {
        $entity = $this->getReference('workflow_aware_entity.1');

        $result = $this->repository->findByClassAndIdentifierAndActiveWorkflows(
            WorkflowAwareEntity::class,
            $entity->getId()
        );

        $this->assertCount(2, $result);
        $this->assertTrue($this->isWorkflowEntityAclIdentityExists($result, 'test_active_flow1', 'step1', 'name'));
        $this->assertTrue($this->isWorkflowEntityAclIdentityExists($result, 'test_active_flow2', 'step1', 'name'));

        $this->getContainer()
            ->get('oro_workflow.manager')
            ->deactivateWorkflow('test_active_flow1');

        $result = $this->repository->findByClassAndIdentifierAndActiveWorkflows(
            WorkflowAwareEntity::class,
            $entity->getId()
        );

        $this->assertCount(1, $result);
        $this->assertFalse($this->isWorkflowEntityAclIdentityExists($result, 'test_active_flow1', 'step1', 'name'));
        $this->assertTrue($this->isWorkflowEntityAclIdentityExists($result, 'test_active_flow2', 'step1', 'name'));
    }

    /**
     * @param array|WorkflowEntityAclIdentity[] $data
     * @param string $workflow
     * @param string $step
     * @param string $attr
     * @return bool
     */
    private function isWorkflowEntityAclIdentityExists(array $data, string $workflow, string $step, string $attr): bool
    {
        $found = false;

        foreach ($data as $identity) {
            $acl = $identity->getAcl();

            if ($identity->getWorkflowItem()->getDefinition()->getName() === $workflow &&
                $acl->getStep()->getName() === $step &&
                $acl->getAttribute() === $attr
            ) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}
