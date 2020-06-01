<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Helper;

use Oro\Bundle\ActionBundle\Model\Attribute;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\SecurityBundle\Acl\Group\AclGroupProviderInterface;
use Oro\Bundle\WorkflowBundle\Acl\AclManager;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;
use Oro\Bundle\WorkflowBundle\Helper\WorkflowDataHelper;
use Oro\Bundle\WorkflowBundle\Model\Step;
use Oro\Bundle\WorkflowBundle\Model\StepManager;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\TransitionManager;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Oro\Bundle\WorkflowBundle\Restriction\RestrictionManager;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Model\Stub\EntityWithWorkflow;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class WorkflowDataHelperTest extends \PHPUnit\Framework\TestCase
{
    /** @var AuthorizationCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $authorizationChecker;

    /** @var TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $translator;

    /** @var UrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $router;

    /** @var AclGroupProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $aclGroupProvider;

    /** @var WorkflowDataHelper */
    protected $workflowDataHelper;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->aclGroupProvider = $this->createMock(AclGroupProviderInterface::class);

        $this->aclGroupProvider->expects($this->any())
            ->method('getGroup')
            ->willReturn(AclGroupProviderInterface::DEFAULT_SECURITY_GROUP);

        $this->authorizationChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $this->router->expects($this->any())
            ->method('generate')
            ->willReturnCallback(function ($route, array $params) {
                return sprintf('%s/%s', $route, implode('/', $params));
            });
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return array
     */
    public function workflowsDataProvider()
    {
        return [
            'workflows' => [
                'workflowsData' => [
                    [
                        'name' => 'started_flow',
                        'transitions' =>
                            [
                                [
                                    'name' => 'open',
                                    'isStart' => false,
                                    'hasForm' => true,
                                    'isAvailable' => true,
                                ],
                                [
                                    'name' => 'close',
                                    'isStart' => false,
                                    'hasForm' => false,
                                    'isAvailable' => true,
                                ],
                                [
                                    'name' => 'disallowed',
                                    'isStart' => false,
                                    'hasForm' => false,
                                    'isAvailable' => true,
                                ],
                                [
                                    'name' => 'unavailable',
                                    'isStart' => false,
                                    'hasForm' => false,
                                    'isAvailable' => false,
                                ],
                            ],
                        'allowed' => ['open', 'close', 'unavailable'],
                        'isStarted' => true,
                    ],
                    [
                        'name' => 'unstarted_flow',
                        'transitions' => [
                            [
                                'name' => 'start',
                                'isStart' => true,
                                'hasForm' => false,
                                'isAvailable' => true,
                            ],
                        ],
                        'allowed' => ['start'],
                        'isStarted' => false,
                    ],
                    [
                        'name' => 'flow_which_cannot_be_started',
                        'transitions' => [
                            [
                                'name' => TransitionManager::DEFAULT_START_TRANSITION_NAME,
                                'isStart' => false,
                                'hasForm' => false,
                                'isAvailable' => false,
                            ],
                        ],
                        'allowed' => [],
                        'isStartStep' => true,
                        'isStarted' => false,
                    ],
                ],
                'expected' => [
                    [
                        'name' => 'started_flow',
                        'label' => 'Started_flow',
                        'isStarted' => true,
                        'workflowItemId' => 1,
                        'transitionsData' => [
                            [
                                'name' => 'open',
                                'label' => null,
                                'isStart' => false,
                                'hasForm' => true,
                                'displayType' => null,
                                'message' => '',
                                'frontendOptions' => null,
                                'transitionUrl' => 'oro_api_workflow_transit/open/1',
                                'dialogUrl' => 'oro_workflow_widget_transition_form/open/1',
                            ],
                            [
                                'name' => 'close',
                                'label' => null,
                                'isStart' => false,
                                'hasForm' => false,
                                'displayType' => null,
                                'message' => '',
                                'frontendOptions' => null,
                                'transitionUrl' => 'oro_api_workflow_transit/close/1',
                            ],
                        ],
                    ],
                    [
                        'name' => 'unstarted_flow',
                        'label' => 'Unstarted_flow',
                        'isStarted' => false,
                        'workflowItemId' => null,
                        'transitionsData' => [
                            [
                                'name' => 'start',
                                'label' => null,
                                'isStart' => true,
                                'hasForm' => false,
                                'displayType' => null,
                                'message' => '',
                                'frontendOptions' => null,
                                'transitionUrl' => 'oro_api_workflow_start/unstarted_flow/start/',
                            ],
                        ],
                    ],
                    [
                        'name' => 'flow_which_cannot_be_started',
                        'label' => 'Flow_which_cannot_be_started',
                        'isStarted' => false,
                        'workflowItemId' => null,
                        'transitionsData' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider workflowsDataProvider
     *
     * @param $workflowsData
     * @param $expected
     */
    public function testGetEntityWorkflowsData($workflowsData, $expected)
    {
        $entity = new EntityWithWorkflow();

        $workflowDataHelper = new WorkflowDataHelper(
            $this->getWorkflowManager($entity, $workflowsData),
            $this->authorizationChecker,
            $this->translator,
            $this->router
        );
        $workflowDataHelper->setAclGroupProvider($this->aclGroupProvider);

        $this->assertEquals($expected, $workflowDataHelper->getEntityWorkflowsData($entity));
    }

    /**
     * @param string $name
     * @param bool $isStart
     * @param bool $hasForm
     * @param bool $isAvailable
     *
     * @return Transition
     */
    protected function getTransition($name, $isStart = false, $hasForm = false, $isAvailable = true)
    {
        $transition = $this->getMockBuilder(Transition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transition->expects($this->any())
            ->method('getName')
            ->willReturn($name);
        $transition->expects($this->any())
            ->method('isAvailable')
            ->willReturn($isAvailable);
        $transition->expects($this->any())
            ->method('isStart')
            ->willReturn($isStart);
        $transition->expects($this->any())
            ->method('hasForm')
            ->willReturn($hasForm);

        /** @var Transition $transition */
        return $transition;
    }

    /**
     * @param object $entity
     * @param array $workflowsData ['name' => string, 'transitions' => array, 'allowed' => array]
     *
     * @return WorkflowManager
     */
    protected function getWorkflowManager($entity, array $workflowsData)
    {
        $workflows = array_map(
            function (array $workflow) {
                return $this->getWorkflow(
                    $workflow['name'],
                    $workflow['transitions'],
                    $workflow['allowed'],
                    $workflow['isStartStep'] ?? false
                );
            },
            $workflowsData
        );
        $workflowManager = $this->getMockBuilder(WorkflowManager::class)->disableOriginalConstructor()->getMock();
        $workflowManager
            ->expects($this->any())
            ->method('getApplicableWorkflows')
            ->willReturn($workflows);

        $workflowItemMap = array_map(
            function (array $workflow) use ($entity) {
                $workflowItem = new WorkflowItem();
                $workflowItem->setId(1);
                $workflowItem->setCurrentStep((new WorkflowStep())->setName('Start'));

                return [$entity, $workflow['name'], $workflow['isStarted'] ? $workflowItem : null];
            },
            $workflowsData
        );

        $workflowManager
            ->expects($this->any())
            ->method('getWorkflowItem')
            ->willReturnMap($workflowItemMap);

        /** @var WorkflowManager $workflowManager */
        return $workflowManager;
    }

    /**
     * Get mocked instance of TransitionManager with test transition definitions
     *
     * @param array $transitionsData ['name' => string, 'isStart' => bool, 'hasForm' => bool, 'isAvailable' => bool]
     *
     * @return TransitionManager
     */
    protected function getTransitionManager(array $transitionsData)
    {
        $extractTransitionsMap = array_map(
            function ($transition) {
                return [
                    $transition['name'],
                    $this->getTransition(
                        $transition['name'],
                        $transition['isStart'],
                        $transition['hasForm'],
                        $transition['isAvailable']
                    ),
                ];
            },
            $transitionsData
        );

        $transitionManager = $this->getMockBuilder(TransitionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transitionManager->expects($this->any())
            ->method('extractTransition')
            ->willReturnMap($extractTransitionsMap);

        $startTransitions = array_filter(
            array_column($extractTransitionsMap, 1),
            function (Transition $transition) {
                return $transition->isStart();
            }
        );

        $transitionManager->expects($this->any())
            ->method('getStartTransitions')
            ->willReturn($startTransitions);


        $defaultTransitions = array_filter(
            array_column($extractTransitionsMap, 1),
            function (Transition $transition) {
                return $transition->getName() === TransitionManager::DEFAULT_START_TRANSITION_NAME;
            }
        );

        $transitionManager->expects($this->any())
            ->method('getDefaultStartTransition')
            ->willReturn(reset($defaultTransitions));

        /** @var TransitionManager $transitionManager */
        return $transitionManager;
    }

    /**
     * @param string $workflowName
     * @param array $transitionsData
     * @param array $allowed
     * @param bool $isStartStep
     *
     * @return Workflow
     */
    protected function getWorkflow($workflowName, array $transitionsData, array $allowed, bool $isStartStep)
    {
        $step = new Step();
        $step->setName('Start');
        $step->setAllowedTransitions($allowed);

        $stepManager = new StepManager([$step]);
        if ($isStartStep) {
            $stepManager->setStartStepName($step->getName());
        }

        /** @var AclManager $aclManager */
        $aclManager = $this->getMockBuilder(AclManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var DoctrineHelper $doctrineHelper */
        $doctrineHelper = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var RestrictionManager|\PHPUnit\Framework\MockObject\MockObject $restrictionManager */
        $restrictionManager = $this->getMockBuilder(RestrictionManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $definition = new WorkflowDefinition();
        $definition->setName($workflowName);
        $definition->setLabel(ucfirst($workflowName));

        $workflow = new Workflow(
            $doctrineHelper,
            $aclManager,
            $restrictionManager,
            $stepManager,
            $attributeManager = null,
            $this->getTransitionManager($transitionsData)
        );

        $workflow->setDefinition($definition);
        $entityAttribute = new Attribute();
        $entityAttribute->setName('entity');
        $workflow->getAttributeManager()->setAttributes([$entityAttribute]);
        $workflow->getAttributeManager()->setEntityAttributeName($entityAttribute->getName());

        return $workflow;
    }
}
