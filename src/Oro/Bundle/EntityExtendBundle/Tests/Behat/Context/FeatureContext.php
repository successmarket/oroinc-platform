<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ImportExportBundle\Tests\Behat\Context\ImportExportContext;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\OroFeatureContext;

class FeatureContext extends OroFeatureContext implements KernelAwareContext
{
    use KernelDictionary;

    /**
     * @var ImportExportContext
     */
    private $importExportContext;

    /**
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->importExportContext = $environment->getContext(ImportExportContext::class);
    }

    /**
     * Download data template for extend entity
     *
     * @When /^(?:|I )download "(?P<entity>([\w\s]+))" extend entity Data Template file$/
     * @param string $entityAlias
     */
    public function iDownloadDataTemplateFileForExtendEntity($entityAlias)
    {
        $className = $this->getContainer()->get('oro_entity.entity_alias_resolver')->getClassByAlias($entityAlias);
        $entityConfigManager = $this->getContainer()->get('oro_entity_config.config_manager');
        $entityModel = $entityConfigManager->getConfigEntityModel($className);

        static::assertNotNull($entityModel, sprintf('No entity model found for class "%s"', $className));

        $this->importExportContext->downloadTemplateFileByProcessor(
            'oro_entity_config_entity_field.export_template',
            ['entity_id' => $entityModel->getId()]
        );
    }

    /**
     * @Given /^(?:|I )check if field "(?P<field>.*)" "(?P<cond>.*)" in db table by entity class "(?P<class>.*)"$/
     *
     * @param string $field
     * @param string $cond
     * @param string $class
     */
    public function checkIfFieldNotOrIsInDbTableByEntityClass(string $field, string $cond, string $class)
    {
        self::assertContains($cond, ['is', 'not']);
        /** @var DoctrineHelper $dh */
        $dh = $this->getContainer()->get('oro_entity.doctrine_helper');

        $em = $dh->getEntityManager($class);
        $sm = $em->getConnection()->getSchemaManager();

        $tableName = $em->getClassMetadata($class)->getTableName();

        $columns = $columns = $sm->listTableColumns($tableName);

        $columnsArray = [];
        foreach ($columns as $column) {
            $columnsArray[] = strtolower($column->getName());
        }

        $field = strtolower($field);
        if ($cond === 'is') {
            self::assertContains($field, $columnsArray);
        } else {
            self::assertNotContains($field, $columnsArray);
        }
    }
}
