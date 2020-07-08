<?php

namespace Oro\Bundle\WorkflowBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowDefinitionRepository;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Helper\WorkflowTranslationHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * List workflow definitions registered within application
 */
class DebugWorkflowDefinitionsCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'oro:debug:workflow:definitions';

    private const INLINE_DEPTH = 20;

    /** @var array */
    protected static $tableHeader = [
        'System Name',
        'Label',
        'Related Entity',
        'Type',
        'Priority',
        'Applications',
        'Exclusive Active Group',
        'Exclusive Record Groups'
    ];

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var TranslatorInterface */
    private $translator;

    /**
     * @param ManagerRegistry $doctrine
     * @param TranslatorInterface $translator
     */
    public function __construct(ManagerRegistry $doctrine, TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->translator = $translator;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('List workflow definitions registered within application')
            ->addArgument(
                'workflow-name',
                InputArgument::OPTIONAL,
                'Name of the workflow definition that should be dumped'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('workflow-name') && $input->getArgument('workflow-name')) {
            return $this->dumpWorkflowDefinition($input->getArgument('workflow-name'), $output);
        }

        return $this->listWorkflowDefinitions($output);
    }

    /**
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function listWorkflowDefinitions(OutputInterface $output)
    {
        /** @var WorkflowDefinition[] $workflows */
        $workflows = $this->getWorkflowDefinitionRepository()->findAll();
        if (count($workflows)) {
            $table = new Table($output);
            $table->setHeaders(self::$tableHeader)->setRows([]);

            foreach ($workflows as $workflow) {
                $activeGroups = implode(', ', $workflow->getExclusiveActiveGroups());
                if (!$activeGroups) {
                    $activeGroups = 'N/A';
                }

                $recordGroups = implode(', ', $workflow->getExclusiveRecordGroups());
                if (!$recordGroups) {
                    $recordGroups = 'N/A';
                }

                $applications = implode(', ', $workflow->getApplications());

                $row = [
                    $workflow->getName(),
                    $this->translator->trans($workflow->getLabel(), [], WorkflowTranslationHelper::TRANSLATION_DOMAIN),
                    $workflow->getRelatedEntity(),
                    $workflow->isSystem() ? 'System' : 'Custom',
                    (int)$workflow->getPriority(),
                    $applications,
                    $activeGroups,
                    $recordGroups
                ];
                $table->addRow($row);
            }
            $table->render();

            return 0;
        }

        $output->writeln('No workflow definitions found.');

        return 1;
    }

    /**
     * @param string $workflowName
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function dumpWorkflowDefinition($workflowName, OutputInterface $output)
    {
        /** @var WorkflowDefinition $workflow */
        $workflow = $this->getWorkflowDefinitionRepository()->findOneBy(['name' => $workflowName]);

        if ($workflow) {
            $general = [
                'entity' => $workflow->getRelatedEntity(),
                'entity_attribute' => $workflow->getEntityAttributeName(),
                'steps_display_ordered' => $workflow->isStepsDisplayOrdered(),
                'priority' => $workflow->getPriority() ?: 0,
                'defaults' => [
                    'active' => $workflow->isActive()
                ],
                WorkflowConfiguration::NODE_APPLICATIONS => $workflow->getApplications()
            ];

            $startStep = $workflow->getStartStep();
            if ($startStep) {
                $general['start_step'] = $startStep->getName();
            }

            if (count($exclusiveActiveGroups = $workflow->getExclusiveActiveGroups())) {
                $general['exclusive_active_groups'] = $exclusiveActiveGroups;
            }

            if (count($exclusiveRecordGroups = $workflow->getExclusiveRecordGroups())) {
                $general['exclusive_record_groups'] = $exclusiveRecordGroups;
            }

            $configuration = $workflow->getConfiguration();

            $this->clearConfiguration($configuration);

            $definition = [
                'workflows' => [
                    $workflow->getName() => array_merge($general, $configuration)
                ]
            ];

            $output->write(Yaml::dump($definition, self::INLINE_DEPTH), true);

            return 0;
        } else {
            $output->writeln('No workflow definitions found.');

            return 1;
        }
    }

    /**
     * Clear "label" and "message" options from configuration
     *
     * @param $array
     */
    protected function clearConfiguration(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $countBefore = count($value);
                $this->clearConfiguration($value);
                if (empty($value) && $countBefore) {
                    $array[$key] = null;
                }
            }
            if (in_array(strtolower($key), ['label', 'message', 'button_label', 'button_title'], true)) {
                unset($array[$key]);
            }
        }
    }

    /**
     * @return WorkflowDefinitionRepository
     */
    protected function getWorkflowDefinitionRepository()
    {
        return $this->doctrine->getRepository(WorkflowDefinition::class);
    }
}
