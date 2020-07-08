<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Model\TransitionTrigger;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowItemRepository;
use Oro\Bundle\WorkflowBundle\Entity\TransitionCronTrigger;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Model\Step;
use Oro\Bundle\WorkflowBundle\Model\StepManager;
use Oro\Bundle\WorkflowBundle\Model\TransitionTrigger\TransitionTriggerCronVerifier;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowAssembler;
use Oro\Bundle\WorkflowBundle\Validator\Expression\ExpressionVerifierInterface;
use Oro\Component\Testing\Unit\EntityTrait;

class TransitionTriggerCronVerifierTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    const ENTITY_CLASS = 'stdClass';
    const ENTITY_ID_FIELD = 'id';

    /** @var WorkflowAssembler|\PHPUnit\Framework\MockObject\MockObject */
    private $workflowAssembler;

    /** @var WorkflowItemRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $workflowItemRepository;

    /** @var ExpressionVerifierInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $cronVerifier;

    /** @var ExpressionVerifierInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $filterVerifier;

    /** @var TransitionTriggerCronVerifier */
    private $verifier;

    protected function setUp(): void
    {
        $this->workflowAssembler = $this->getMockBuilder(WorkflowAssembler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->workflowItemRepository = $this->getMockBuilder(WorkflowItemRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = new ClassMetadataInfo(self::ENTITY_CLASS);
        $metadata->setIdentifier([self::ENTITY_ID_FIELD]);

        $em = $this->createMock(ObjectManager::class);
        $em->expects($this->any())
            ->method('getRepository')
            ->with(WorkflowItem::class)
            ->willReturn($this->workflowItemRepository);
        $em->expects($this->any())
            ->method('getClassMetadata')
            ->with(self::ENTITY_CLASS)
            ->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($this->logicalOr(WorkflowItem::class, self::ENTITY_CLASS))
            ->willReturn($em);

        $this->cronVerifier = $this->createMock(ExpressionVerifierInterface::class);
        $this->filterVerifier = $this->createMock(ExpressionVerifierInterface::class);

        $this->verifier = new TransitionTriggerCronVerifier($this->workflowAssembler, $registry);
    }

    public function testAddOptionVerifier()
    {
        $trigger = $this->createMock(TransitionCronTrigger::class);
        $trigger->expects(static::any())
            ->method('getCron')
            ->willReturn('something');

        $verifier1 = $this->createMock(ExpressionVerifierInterface::class);
        $verifier1->expects(static::exactly(2))
            ->method('verify');

        $this->verifier->addOptionVerifier('cron', $verifier1);

        $this->verifier->verify($trigger);

        $verifier2 = $this->createMock(ExpressionVerifierInterface::class);
        $verifier2->expects(static::exactly(1))
            ->method('verify');

        $this->verifier->addOptionVerifier('cron', $verifier2);
        $this->verifier->verify($trigger);
    }

    public function testVerify()
    {
        $cron = '* * * * *';
        $filter = 'e.test = data';
        $query = $this->getMockBuilder(AbstractQuery::class)->disableOriginalConstructor()->getMock();
        $transitionName = 'test_transition';
        $expectedStep = $this->getStep('step2', [$transitionName]);
        $workflow = $this->getWorkflow([$this->getStep('step1', ['invalid_transition']), $expectedStep]);

        $this->cronVerifier->expects($this->once())->method('verify')->with($cron);
        $this->filterVerifier->expects($this->once())->method('verify')->with($query);

        $testVerifier = $this->createMock(ExpressionVerifierInterface::class);
        $testVerifier->expects($this->never())->method($this->anything());

        $this->verifier->addOptionVerifier('cron', $this->cronVerifier);
        $this->verifier->addOptionVerifier('filter', $this->filterVerifier);
        $this->verifier->addOptionVerifier('test', $testVerifier);

        $this->workflowAssembler->expects($this->once())
            ->method('assemble')
            ->with($workflow->getDefinition(), false)
            ->willReturn($workflow);

        $this->workflowItemRepository->expects($this->once())
            ->method('findByStepNamesAndEntityClassQueryBuilder')
            ->with(
                new ArrayCollection([$expectedStep->getName() => $expectedStep->getName()]),
                self::ENTITY_CLASS,
                self::ENTITY_ID_FIELD,
                $filter
            )
            ->willReturn($this->setUpQueryBuilder($query));

        $this->verifier->verify($this->getTrigger($workflow, $transitionName, $cron, $filter));
    }

    /**
     * @param Workflow $workflow
     * @param string $transitionName
     * @param string $cron
     * @param string $filter
     * @return TransitionCronTrigger|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getTrigger(Workflow $workflow, $transitionName, $cron, $filter)
    {
        $trigger = $this->getMockBuilder(TransitionCronTrigger::class)->disableOriginalConstructor()->getMock();
        $trigger->expects($this->any())->method('getCron')->willReturn($cron);
        $trigger->expects($this->any())->method('getFilter')->willReturn($filter);
        $trigger->expects($this->any())->method('getWorkflowDefinition')->willReturn($workflow->getDefinition());
        $trigger->expects($this->any())->method('getTransitionName')->willReturn($transitionName);
        $trigger->expects($this->any())->method('getEntityClass')->willReturn(self::ENTITY_CLASS);

        return $trigger;
    }

    /**
     * @param array $steps
     * @return Workflow|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getWorkflow(array $steps)
    {
        $workflowDefinition = $this->getEntity(WorkflowDefinition::class, ['relatedEntity' => self::ENTITY_CLASS]);
        $stepManager = new StepManager($steps);

        $workflow = $this->getMockBuilder(Workflow::class)->disableOriginalConstructor()->getMock();
        $workflow->expects($this->any())->method('getDefinition')->willReturn($workflowDefinition);
        $workflow->expects($this->any())->method('getStepManager')->willReturn($stepManager);

        return $workflow;
    }

    /**
     * @param string $name
     * @param array $allowedTransitions
     * @return Step
     */
    protected function getStep($name, array $allowedTransitions)
    {
        $step = new Step();
        $step->setName($name)->setAllowedTransitions($allowedTransitions);

        return $step;
    }

    /**
     * @param AbstractQuery $query
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function setUpQueryBuilder(AbstractQuery $query)
    {
        /** @var QueryBuilder|\PHPUnit\Framework\MockObject\MockObject $qb */
        $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        return $qb;
    }
}
