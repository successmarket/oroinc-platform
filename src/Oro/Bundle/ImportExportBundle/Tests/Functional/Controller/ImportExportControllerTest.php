<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Functional\Controller;

use Oro\Bundle\ImportExportBundle\Async\Topics;
use Oro\Bundle\ImportExportBundle\Configuration\ImportExportConfiguration;
use Oro\Bundle\ImportExportBundle\Configuration\ImportExportConfigurationInterface;
use Oro\Bundle\ImportExportBundle\Configuration\ImportExportConfigurationProviderInterface;
use Oro\Bundle\ImportExportBundle\Controller\ImportExportController;
use Oro\Bundle\ImportExportBundle\Entity\ImportExportResult;
use Oro\Bundle\ImportExportBundle\Form\Type\ImportType;
use Oro\Bundle\ImportExportBundle\Job\JobExecutor;
use Oro\Bundle\ImportExportBundle\Tests\Functional\DataFixtures\LoadImportExportResultData;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Component\Testing\TempDirExtension;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class ImportExportControllerTest extends WebTestCase
{
    use MessageQueueExtension;
    use TempDirExtension;

    /**
     * @var array
     */
    private $existingFiles = [];

    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);

        $this->loadFixtures([
            LoadImportExportResultData::class
        ]);

        $this->existingFiles = glob($this->getImportDir() . DIRECTORY_SEPARATOR . '*.csv');
    }

    protected function tearDown(): void
    {
        $tempFiles = glob($this->getImportDir() . DIRECTORY_SEPARATOR . '*.csv');
        $diffFiles = array_diff($tempFiles, $this->existingFiles);
        foreach ($diffFiles as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testShouldSendExportMessageOnInstantExportActionWithDefaultParameters()
    {
        $this->ajaxRequest(
            'POST',
            $this->getUrl('oro_importexport_export_instant', ['processorAlias' => 'oro_account'])
        );

        $this->assertJsonResponseSuccessOnExport();

        $organization = $this->getTokenAccessor()->getOrganization();
        $organizationId = $organization ? $organization->getId() : null;

        $this->assertMessageSent(Topics::PRE_EXPORT, [
            'jobName' => JobExecutor::JOB_EXPORT_TO_CSV,
            'processorAlias' => 'oro_account',
            'outputFilePrefix' => null,
            'options' => [],
            'userId' => $this->getCurrentUser()->getId(),
            'organizationId' => $organizationId,
        ]);
    }

    public function testShouldSendExportMessageOnInstantExportActionWithPassedParameters()
    {
        $this->ajaxRequest(
            'POST',
            $this->getUrl('oro_importexport_export_instant', [
                'processorAlias' => 'oro_account',
                'exportJob' => JobExecutor::JOB_EXPORT_TEMPLATE_TO_CSV,
                'filePrefix' => 'prefix',
                'options' => [
                    'first' => 'first value',
                    'second' => 'second value',
                ]
            ])
        );

        $this->assertJsonResponseSuccessOnExport();

        $organization = $this->getTokenAccessor()->getOrganization();
        $organizationId = $organization ? $organization->getId() : null;

        $this->assertMessageSent(Topics::PRE_EXPORT, [
            'jobName' => JobExecutor::JOB_EXPORT_TEMPLATE_TO_CSV,
            'processorAlias' => 'oro_account',
            'outputFilePrefix' => 'prefix',
            'options' => [
                'first' => 'first value',
                'second' => 'second value',
            ],
            'userId' => $this->getCurrentUser()->getId(),
            'organizationId' => $organizationId,
        ]);
    }

    public function testDownloadFileReturns404IfFileDoesntExist()
    {
        $undefinedJobId = 999;
        $this->client->followRedirects(true);

        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_export_download', [
                'jobId' => $undefinedJobId
            ])
        );

        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);
    }

    public function testImportProcessAction()
    {
        $options = [
            'first' => 'first value',
            'second' => 'second value',
        ];
        $this->ajaxRequest(
            'POST',
            $this->getUrl(
                'oro_importexport_import_process',
                [
                    'processorAlias' => 'oro_account',
                    'importJob' => JobExecutor::JOB_IMPORT_FROM_CSV,
                    'fileName' => 'test_file',
                    'originFileName' => 'test_file_original',
                    'options' => $options,
                ]
            )
        );

        $this->assertJsonResponseSuccess();

        $this->assertMessageSent(
            Topics::PRE_IMPORT,
            [
                'jobName' => JobExecutor::JOB_IMPORT_FROM_CSV,
                'process' => 'import',
                'processorAlias' => 'oro_account',
                'fileName' => 'test_file',
                'originFileName' => 'test_file_original',
                'options' => $options,
                'userId' => $this->getCurrentUser()->getId(),
            ]
        );
    }

    public function testImportValidateAction()
    {
        $options = [
            'first' => 'first value',
            'second' => 'second value',
        ];
        $this->ajaxRequest(
            'POST',
            $this->getUrl(
                'oro_importexport_import_validate',
                [
                    'processorAlias' => 'oro_account',
                    'importValidateJob' => JobExecutor::JOB_IMPORT_VALIDATION_FROM_CSV,
                    'fileName' => 'test_file',
                    'originFileName' => 'test_file_original',
                    'options' => $options,
                ]
            )
        );

        $this->assertJsonResponseSuccess();

        $this->assertMessageSent(
            Topics::PRE_IMPORT,
            [
                'jobName' => JobExecutor::JOB_IMPORT_VALIDATION_FROM_CSV,
                'processorAlias' => 'oro_account',
                'process' => 'import_validation',
                'fileName' => 'test_file',
                'originFileName' => 'test_file_original',
                'options' => $options,
                'userId' => $this->getCurrentUser()->getId(),
            ]
        );
    }

    public function testImportForm()
    {
        $fileName = 'oro_testLineEndings.csv';
        $importedFilePath = null;

        $file = $this->copyToTempDir('import_export', __DIR__ . '/Import/fixtures')
            . DIRECTORY_SEPARATOR
            . $fileName;
        $csvFile = new UploadedFile(
            $file,
            $fileName,
            'text/csv'
        );
        $this->assertEquals(
            substr_count(file_get_contents($file), "\r\n"),
            substr_count(file_get_contents($csvFile->getPathname()), "\r\n")
        );
        $this->assertEquals(
            substr_count(file_get_contents($file), "\n"),
            substr_count(file_get_contents($csvFile->getPathname()), "\n")
        );

        $crawler = $this->client->request(
            'GET',
            $this->getUrl(
                'oro_importexport_import_form',
                [
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test',
                    'entity' => User::class,
                ]
            )
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        $uploadFileNode = $crawler->selectButton('Submit');
        $uploadFileForm = $uploadFileNode->form();
        $values = [
            'oro_importexport_import' => [
                '_token' => $uploadFileForm['oro_importexport_import[_token]']->getValue(),
                'processorAlias' => 'oro_user.add_or_replace'
            ],
        ];
        $files = [
            'oro_importexport_import' => [
                'file' => $csvFile
            ]
        ];
        $this->client->request(
            $uploadFileForm->getMethod(),
            $this->getUrl(
                'oro_importexport_import_form',
                [
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test',
                    'entity' => User::class,
                ]
            ),
            $values,
            $files
        );
        $this->assertJsonResponseSuccess();
        $message = $this->getSentMessage(Topics::PRE_IMPORT);
        $importedFilePath = $this->getImportDir() . DIRECTORY_SEPARATOR . $message['fileName'];
        $this->assertEquals(
            substr_count(file_get_contents($file), "\n"),
            substr_count(file_get_contents($importedFilePath), "\r\n")
        );
    }

    public function testImportValidateExportTemplateFormNoAlias()
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_import_validate_export_template_form')
        );

        static::assertResponseStatusCodeEquals($this->client->getResponse(), 400);
    }

    public function testImportValidateExportTemplateFormGetRequest()
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_import_validate_export_template_form'),
            [
                'alias' => 'alias',
                'entity' => 'entity',
            ]
        );

        $response = $this->client->getResponse();

        static::assertResponseStatusCodeEquals($response, 200);
        static::assertStringContainsString('Cancel', $response->getContent());
        static::assertStringContainsString('Validate', $response->getContent());
        static::assertStringContainsString('Import file', $response->getContent());
    }

    public function testImportValidateExportTemplateFormAction(): void
    {
        $registry = $this->getContainer()->get('oro_importexport.configuration.registry');
        $registry->addConfiguration(
            new class() implements ImportExportConfigurationProviderInterface {
                /**
                 * {@inheritdoc}
                 */
                public function get(): ImportExportConfigurationInterface
                {
                    return new ImportExportConfiguration([
                        ImportExportConfiguration::FIELD_ENTITY_CLASS => \stdClass::class,
                    ]);
                }
            },
            'oro_test'
        );

        $controller = $this->getContainer()->get(ImportExportController::class);

        $this->assertEquals(
            [
                'options' => [],
                'alias' => 'oro_test',
                'configsWithForm' => [],
                'chosenEntityName' => \stdClass::class
            ],
            $controller->importValidateExportTemplateFormAction(
                new Request(['alias' => 'oro_test', 'entity' => \stdClass::class])
            )
        );

        $registry->addConfiguration(
            new class() implements ImportExportConfigurationProviderInterface {
                /**
                 * {@inheritdoc}
                 */
                public function get(): ImportExportConfigurationInterface
                {
                    return new ImportExportConfiguration([
                        ImportExportConfiguration::FIELD_ENTITY_CLASS => \stdClass::class,
                        ImportExportConfiguration::FIELD_IMPORT_PROCESSOR_ALIAS => 'oro_test',
                    ]);
                }
            },
            'oro_test'
        );

        $formFactory = $this->getContainer()->get('form.factory');

        $this->assertEquals(
            [
                'options' => [],
                'alias' => 'oro_test',
                'configsWithForm' => [
                    [
                        'form' => $formFactory->create(ImportType::class, null, ['entityName' => \stdClass::class]),
                        'configuration' => new ImportExportConfiguration([
                            ImportExportConfiguration::FIELD_ENTITY_CLASS => \stdClass::class,
                            ImportExportConfiguration::FIELD_IMPORT_PROCESSOR_ALIAS => 'oro_test',
                        ])
                    ]
                ],
                'chosenEntityName' => \stdClass::class
            ],
            $controller->importValidateExportTemplateFormAction(
                new Request(['alias' => 'oro_test', 'entity' => \stdClass::class])
            )
        );
    }

    public function testDownloadExportResultActionExpiredResult()
    {
        /** @var ImportExportResult $expiredImportExportResult */
        $expiredImportExportResult = $this->getReference('expiredImportExportResult');

        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_export_download', [
                'jobId' => $expiredImportExportResult->getJobId()
            ])
        );

        $this->assertJsonResponseStatusCodeEquals($this->client->getResponse(), 410);
    }

    public function testImportExportJobErrorLogActionExpiredResult()
    {
        /** @var ImportExportResult $expiredImportExportResult */
        $expiredImportExportResult = $this->getReference('expiredImportExportResult');

        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_job_error_log', [
                'jobId' => $expiredImportExportResult->getJobId()
            ])
        );

        $this->assertJsonResponseStatusCodeEquals($this->client->getResponse(), 410);
    }

    /**
     * @return string
     */
    private function getImportDir()
    {
        return $this->getContainer()->getParameter('kernel.project_dir') . '/var/import_export';
    }

    /**
     * @return TokenAccessorInterface
     */
    private function getTokenAccessor()
    {
        return $this->getContainer()->get('oro_security.token_accessor');
    }

    /**
     * @return mixed
     */
    private function getCurrentUser()
    {
        return $this->getContainer()->get('security.token_storage')->getToken()->getUser();
    }

    private function assertJsonResponseSuccessOnExport()
    {
        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertNotEmpty($result);
        $this->assertCount(1, $result);
        $this->assertTrue($result['success']);
    }

    private function assertJsonResponseSuccess()
    {
        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);
        $this->assertTrue($result['success']);
        static::assertContainsEquals('message', $result);
    }
}
