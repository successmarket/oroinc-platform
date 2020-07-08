<?php

namespace Oro\Bundle\QueryDesignerBundle\Tests\Functional\QueryDesigner;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\SubQueryLimitHelper;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\SubQueryLimitOutputResultModifier;
use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\DoctrineUtils\ORM\Walker\SqlWalker;

class SubQueryLimitHelperTest extends WebTestCase
{
    /** @var SubQueryLimitHelper */
    private $helper;

    protected function setUp(): void
    {
        $this->initClient();
        $this->helper = new SubQueryLimitHelper();
    }

    public function testSetLimit()
    {
        $testQb = $this->getContainer()
            ->get('oro_entity.doctrine_helper')
            ->getEntityRepository(WorkflowAwareEntity::class)
            ->createQueryBuilder('testQb');

        $testQb = $this->helper->setLimit($testQb, 777, 'id');
        $this->assertEquals(SqlWalker::class, $testQb->getQuery()->getHint(Query::HINT_CUSTOM_OUTPUT_WALKER));
        $this->assertEquals(
            777,
            $testQb->getQuery()->getHint(SubQueryLimitOutputResultModifier::WALKER_HOOK_LIMIT_VALUE)
        );
        $this->assertEquals(
            'id',
            $testQb->getQuery()->getHint(SubQueryLimitOutputResultModifier::WALKER_HOOK_LIMIT_ID)
        );
        static::assertStringContainsString(
            SubQueryLimitOutputResultModifier::WALKER_HOOK_LIMIT_KEY,
            $testQb->getQuery()->getHint(SubQueryLimitOutputResultModifier::WALKER_HOOK_LIMIT_KEY)
        );
    }

    public function testDeleteAfterSetLimit()
    {
        $this->loadFixtures(['@OroQueryDesignerBundle/Tests/Functional/DataFixtures/workflows.yml']);
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManagerForClass(WorkflowAwareEntity::class);
        $repo = $em->getRepository(WorkflowAwareEntity::class);

        $subSelectQb = $repo->createQueryBuilder('sub_we');
        $subSelectQb = $this->helper->setLimit($subSelectQb, 4, 'id');

        $selectQb = $repo->createQueryBuilder('we');
        $selectQb->where($selectQb->expr()->in('we.id', $subSelectQb->getDQL()));

        $this->assertCount(4, $selectQb->getQuery()->getResult());

        $deleteQb = $em->createQueryBuilder()->delete(WorkflowAwareEntity::class, 'we');
        $deleteQb->getQuery()->execute();

        $this->assertCount(0, $repo->findAll());
    }
}
