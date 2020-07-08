<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetConfig;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\ApiBundle\ApiDoc\EntityDescriptionProvider;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocParserInterface;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocParserRegistry;
use Oro\Bundle\ApiBundle\ApiDoc\ResourceDocProvider;
use Oro\Bundle\ApiBundle\Config\FiltersConfig;
use Oro\Bundle\ApiBundle\Model\Label;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\DescriptionProcessor;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\EntityDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\FeatureDependedTextProcessor;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\FieldsDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\FiltersDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\IdentifierDescriptionHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\RequestDependedTextProcessor;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDescriptions\ResourceDocParserProvider;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\ProductPrice as TestEntity;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Tests\Unit\ConfigProviderMock;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Component\Testing\Unit\Entity\Stub\StubEnumValue as EnumEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class CompleteDescriptionsTest extends ConfigProcessorTestCase
{
    private const ID_DESCRIPTION                 = 'The unique identifier of a resource.';
    private const REQUIRED_ID_DESCRIPTION        = '<p>The unique identifier of a resource.</p>'
    . '<p><strong>The required field.</strong></p>';
    private const CREATED_AT_DESCRIPTION         = 'The date and time of resource record creation.';
    private const UPDATED_AT_DESCRIPTION         = 'The date and time of the last update of the resource record.';
    private const OWNER_DESCRIPTION              = 'An owner record represents'
    . ' the ownership capabilities of the record.';
    private const ORGANIZATION_DESCRIPTION       = 'An organization record represents'
    . ' a real enterprise, business, firm, company or another organization to which the users belong.';
    private const ENUM_NAME_DESCRIPTION          = 'The human readable name of the option.';
    private const ENUM_DEFAULT_DESCRIPTION       = 'Determines if this option is selected by default'
    . ' for new records.';
    private const ENUM_PRIORITY_DESCRIPTION      = 'The order in which options are ranked.'
    . ' First appears the option with the higher number of the priority.';
    private const FIELD_FILTER_DESCRIPTION       = 'Filter records by \'%s\' field.';
    private const ASSOCIATION_FILTER_DESCRIPTION = 'Filter records by \'%s\' relationship.';

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityDescriptionProvider */
    private $entityDocProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ResourceDocProvider */
    private $resourceDocProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ResourceDocParserRegistry */
    private $resourceDocParserRegistry;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ResourceDocParserInterface */
    private $resourceDocParser;

    /** @var \PHPUnit\Framework\MockObject\MockObject|TranslatorInterface */
    private $translator;

    /** @var \PHPUnit\Framework\MockObject\MockObject|DoctrineHelper */
    private $doctrineHelper;

    /** @var ConfigProviderMock */
    private $ownershipConfigProvider;

    /** @var CompleteDescriptions */
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityDocProvider = $this->createMock(EntityDescriptionProvider::class);
        $this->resourceDocProvider = $this->createMock(ResourceDocProvider::class);
        $this->resourceDocParserRegistry = $this->createMock(ResourceDocParserRegistry::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->ownershipConfigProvider = new ConfigProviderMock($this->createMock(ConfigManager::class), 'ownership');

        $this->resourceDocParser = $this->createMock(ResourceDocParserInterface::class);
        $this->resourceDocParserRegistry->expects(self::any())
            ->method('getParser')
            ->willReturn($this->resourceDocParser);

        $resourceDocParserProvider = new ResourceDocParserProvider($this->resourceDocParserRegistry);
        $descriptionProcessor = new DescriptionProcessor(
            new RequestDependedTextProcessor(),
            new FeatureDependedTextProcessor($this->createMock(FeatureChecker::class))
        );
        $identifierDescriptionHelper = new IdentifierDescriptionHelper($this->doctrineHelper);

        $this->processor = new CompleteDescriptions(
            $this->createMock(ResourcesProvider::class),
            new EntityDescriptionHelper(
                $this->entityDocProvider,
                $this->translator,
                $this->resourceDocProvider,
                $resourceDocParserProvider,
                $descriptionProcessor,
                $identifierDescriptionHelper
            ),
            new FieldsDescriptionHelper(
                $this->entityDocProvider,
                $this->translator,
                $resourceDocParserProvider,
                $descriptionProcessor,
                $identifierDescriptionHelper,
                $this->ownershipConfigProvider
            ),
            new FiltersDescriptionHelper(
                $this->translator,
                $resourceDocParserProvider,
                $descriptionProcessor
            )
        );

        $this->context->setClassName(TestEntity::class);
    }

    public function testWithoutTargetAction()
    {
        $config = [
            'exclusion_policy'       => 'all',
            'identifier_field_names' => ['id'],
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_field_names' => ['id'],
                'fields'                 => [
                    'id'     => null,
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierDescriptionWhenItDoesNotExist()
    {
        $config = [];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierDescriptionWhenItAlreadyExists()
    {
        $config = [
            'identifier_description' => 'identifier description'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => 'identifier description'
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierField()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForUpdateAction()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('update');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndNotManageableEntity()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(false);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithoutIdGenerator()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(false);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithIdGenerator()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldForCreateActionAndManageableEntityWithIdGeneratorButApiIdNotEqualEntityId()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $classMetadata = $this->createMock(ClassMetadata::class);
        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with(TestEntity::class)
            ->willReturn(true);
        $this->doctrineHelper->expects(self::once())
            ->method('getEntityMetadataForClass')
            ->with(TestEntity::class)
            ->willReturn($classMetadata);
        $classMetadata->expects(self::once())
            ->method('usesIdGenerator')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['entityId']);

        $this->context->setTargetAction('create');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => self::REQUIRED_ID_DESCRIPTION
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldWhenItAlreadyHasDescription()
    {
        $config = [
            'identifier_field_names' => ['id'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'     => [
                    'description' => 'existing description'
                ],
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => [
                        'description' => 'existing description'
                    ],
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedIdentifierField()
    {
        $config = [
            'identifier_field_names' => ['id1'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'  => null,
                'id1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id1'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'  => null,
                    'id1' => [
                        'description' => self::ID_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCompositeIdentifierField()
    {
        $config = [
            'identifier_field_names' => ['id1', 'id2'],
            'exclusion_policy'       => 'all',
            'fields'                 => [
                'id'  => null,
                'id1' => null,
                'id2' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_field_names' => ['id1', 'id2'],
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'  => null,
                    'id1' => null,
                    'id2' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testIdentifierFieldDoesNotExist()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'id'     => null,
                'field1' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'     => null,
                    'field1' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCreatedAtField()
    {
        $config = [
            'fields' => [
                'id'        => null,
                'createdAt' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'createdAt' => [
                        'description' => self::CREATED_AT_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testCreatedAtFieldWhenItAlreadyHasDescription()
    {
        $config = [
            'fields' => [
                'id'        => null,
                'createdAt' => [
                    'description' => 'existing description'
                ]
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'createdAt' => [
                        'description' => 'existing description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testUpdatedAtField()
    {
        $config = [
            'fields' => [
                'id'        => null,
                'created'   => null,
                'updatedAt' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'created'   => null,
                    'updatedAt' => [
                        'description' => self::UPDATED_AT_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testUpdatedAtFieldWhenItAlreadyHasDescription()
    {
        $config = [
            'fields' => [
                'id'        => null,
                'updatedAt' => [
                    'description' => 'existing description'
                ]
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'id'        => null,
                    'updatedAt' => [
                        'description' => 'existing description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnershipFieldsForNonConfigurableEntity()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnershipFieldsWithoutConfiguration()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            []
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOwnerField()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['owner_field_name' => 'owner']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => [
                        'description' => self::OWNER_DESCRIPTION
                    ],
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testOrganizationField()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'        => null,
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['organization_field_name' => 'organization']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'        => null,
                    'organization' => [
                        'description' => self::ORGANIZATION_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedOwnerField()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner2'       => ['property_path' => 'owner1'],
                'organization' => null
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['owner_field_name' => 'owner1']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner2'       => [
                        'property_path' => 'owner1',
                        'description'   => self::OWNER_DESCRIPTION
                    ],
                    'organization' => null
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRenamedOrganizationField()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'owner'         => null,
                'organization2' => ['property_path' => 'organization1']
            ]
        ];

        $this->ownershipConfigProvider->addEntityConfig(
            TestEntity::class,
            ['organization_field_name' => 'organization1']
        );

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'owner'         => null,
                    'organization2' => [
                        'property_path' => 'organization1',
                        'description'   => self::ORGANIZATION_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testEnumFields()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'name'     => null,
                'default'  => null,
                'priority' => null
            ]
        ];

        $this->context->setClassName(EnumEntity::class);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'name'     => [
                        'description' => self::ENUM_NAME_DESCRIPTION
                    ],
                    'default'  => [
                        'description' => self::ENUM_DEFAULT_DESCRIPTION
                    ],
                    'priority' => [
                        'description' => self::ENUM_PRIORITY_DESCRIPTION
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfig()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description'
                ]
            ]
        ];

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItIsLabelObject()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => new Label('field description label')
                ]
            ]
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('field description label')
            ->willReturn('translated field description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'translated field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfigAndContainsInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description, {@inheritdoc}'
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForRenamedFieldWhenItExistsInConfigAndContainsInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'renamedField' => [
                    'property_path' => 'testField',
                    'description'   => 'field description, {@inheritdoc}'
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'renamedField' => [
                        'property_path' => 'testField',
                        'description'   => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInConfigAndContainsDescriptionInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'field description, {@inheritdoc:description}'
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description, field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInDocFile()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField', $targetAction)
            ->willReturn('field description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInDocFileAndContainsInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description'],
                [$entityClass, 'testField', $targetAction, 'action field description. {@inheritdoc}']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. common field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItAndCommonDescriptionExistInDocFileAndContainsInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description. {@inheritdoc}'],
                [$entityClass, 'testField', $targetAction, 'action field description. {@inheritdoc}']
            ]);
        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. common field description. '
                            . 'field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItExistsInDocFileAndContainsInheritDocPlaceholderButNoAndCommonDescription()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, null],
                [$entityClass, 'testField', $targetAction, 'action field description. {@inheritdoc}']
            ]);
        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'action field description. field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItDoesNotExistInDocFileButExistCommonDescription()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description'],
                [$entityClass, 'testField', $targetAction, null]
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'common field description'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItDoesNotExistInDocFileButExistCommonDescriptionWithInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, 'common field description. {@inheritdoc}'],
                [$entityClass, 'testField', $targetAction, null]
            ]);
        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'common field description. field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionWhenItAndCommonDescriptionDoNotExistInDocFile()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null, null],
                [$entityClass, 'testField', $targetAction, null]
            ]);
        $this->entityDocProvider->expects(self::once())
            ->method('getFieldDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('field description from the entity config');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'description' => 'field description from the entity config'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForNestedField()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'nestedField' => null
                    ]
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'fields' => [
                            'nestedField' => [
                                'description' => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForNestedFieldWhenFieldIsRenamed()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'renamedField' => [
                    'property_path' => 'testField',
                    'fields'        => [
                        'nestedField' => null
                    ]
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'renamedField' => [
                        'property_path' => 'testField',
                        'fields'        => [
                            'nestedField' => [
                                'description' => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForRenamedNestedField()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'renamedNestedField' => [
                            'property_path' => 'nestedField'
                        ]
                    ]
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'testField.nestedField', 'nested field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'fields' => [
                            'renamedNestedField' => [
                                'property_path' => 'nestedField',
                                'description'   => 'nested field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFieldDescriptionForAssociationField()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'target_class' => 'Test\AssociationEntity',
                    'fields'       => [
                        'associationField' => null
                    ]
                ]
            ]
        ];

        $this->entityDocProvider->expects(self::exactly(2))
            ->method('getFieldDocumentation')
            ->willReturnMap([
                [$entityClass, 'testField', null],
                [$entityClass, 'associationField', 'association field description']
            ]);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'testField' => [
                        'target_class' => 'Test\AssociationEntity',
                        'fields'       => [
                            'associationField' => [
                                'description' => 'association field description'
                            ]
                        ]
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testFilterDescriptionWhenItExistsInConfig()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => 'filter description'
                ]
            ]
        ];

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionWhenItIsLabelObject()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'description' => new Label('filter description label')
                ]
            ]
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('filter description label')
            ->willReturn('translated filter description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'translated filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionWhenItExistsInDocFile()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn('filter description');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => 'filter description'
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionForRegularField()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn(null);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => sprintf(self::FIELD_FILTER_DESCRIPTION, 'testField')
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testFilterDescriptionForAssociation()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => [
                    'fields' => [
                        'id' => null
                    ]
                ]
            ]
        ];
        $filters = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'testField' => null
            ]
        ];

        $this->resourceDocParser->expects(self::once())
            ->method('getFilterDocumentation')
            ->with($entityClass, 'testField')
            ->willReturn(null);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->context->setFilters($this->createConfigObject($filters, ConfigUtil::FILTERS));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all',
                'fields'           => [
                    'testField' => [
                        'description' => sprintf(self::ASSOCIATION_FILTER_DESCRIPTION, 'testField')
                    ]
                ]
            ],
            $this->context->getFilters()
        );
    }

    public function testRequestDependedContentForEntityDocumentation()
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => '{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}'
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'JSON API'
            ],
            $this->context->getResult()
        );
    }

    public function testRequestDependedContentForFieldDescription()
    {
        $config = [
            'exclusion_policy' => 'all',
            'fields'           => [
                'field1' => [
                    'description' => '{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}'
                ]
            ]
        ];

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'fields'                 => [
                    'field1' => [
                        'description' => 'JSON API'
                    ]
                ]
            ],
            $this->context->getResult()
        );
    }

    public function testRequestDependedContentForFilterDescription()
    {
        $filters = new FiltersConfig();
        $filter1 = $filters->addField('field1');
        $filter1->setDescription('{@request:json_api}JSON API{@/request}{@request:rest}REST{@/request}');

        $this->context->getRequestType()->set(new RequestType([RequestType::JSON_API]));
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject([]));
        $this->context->setFilters($filters);
        $this->processor->process($this->context);

        self::assertEquals('JSON API', $filter1->getDescription());
    }

    public function testPrimaryResourceDescriptionWhenItExistsInConfig()
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => 'test description'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'test description'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionWhenItExistsInConfig()
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => 'test description'
        ];

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'test description'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenItIsLabelObject()
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => new Label('description_label')
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('description_label')
            ->willReturn('translated description');

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'translated description'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionWhenItIsLabelObject()
    {
        $config = [
            'exclusion_policy' => 'all',
            'description'      => new Label('description_label')
        ];

        $this->translator->expects(self::once())
            ->method('trans')
            ->with('description_label')
            ->willReturn('translated description');

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => 'translated description'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenEntityDocProviderReturnsNull()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'Product Price';
        $actionDescription = 'Get Product Price';

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn(null);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionWhenEntityDocProviderReturnsNullForCollectionResource()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'Product Prices';
        $actionDescription = 'Get list of Product Prices';

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn(null);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionLoadedByEntityDocProvider()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'some entity';
        $actionDescription = 'Get some entity';

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn($entityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionLoadedByEntityDocProvider()
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDescription = 'Get test association';

        $this->entityDocProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDescription')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDescription);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $subresourceDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDescriptionLoadedByEntityDocProviderForCollectionResource()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $entityDescription = 'some entities';
        $actionDescription = 'Get list of some entities';

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn($entityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDescription')
            ->with($targetAction, $entityDescription)
            ->willReturn($actionDescription);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $actionDescription
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDescriptionLoadedByEntityDocProviderForCollectionResource()
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDescription = 'Get test association';

        $this->entityDocProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDescription')
            ->with($targetAction, $associationDescription, true)
            ->willReturn($subresourceDescription);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'description'            => $subresourceDescription
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceRegisterDocumentationResources()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy'       => 'all',
            'documentation_resource' => ['foo_file.md', 'bar_file.md']
        ];
        $actionDocumentation = 'action description';

        $this->resourceDocParser->expects(self::at(0))
            ->method('registerDocumentationResource')
            ->with('foo_file.md');
        $this->resourceDocParser->expects(self::at(1))
            ->method('registerDocumentationResource')
            ->with('bar_file.md');
        $this->resourceDocParser->expects(self::at(2))
            ->method('getActionDocumentation')
            ->with($entityClass, $targetAction)
            ->willReturn($actionDocumentation);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'documentation_resource' => ['foo_file.md', 'bar_file.md'],
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $actionDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceRegisterDocumentationResources()
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy'       => 'all',
            'documentation_resource' => ['documentation.md']
        ];
        $subresourceDocumentation = 'subresource description';

        $this->resourceDocParser->expects(self::once())
            ->method('registerDocumentationResource')
            ->with('documentation.md');
        $this->resourceDocParser->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($parentEntityClass, $associationName, $targetAction)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'documentation_resource' => ['documentation.md'],
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWhenItExistsInConfig()
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ];

        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationWhenItExistsInConfig()
    {
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'test documentation'
        ];

        $this->context->setParentClassName(TestEntity::class);
        $this->context->setAssociationName('testAssociation');
        $this->context->setTargetAction('get_subresource');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'test documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWithInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'action documentation. {@inheritdoc}'
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn('entity documentation');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'action documentation. entity documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationWithDescriptionInheritDocPlaceholder()
    {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => 'action documentation. {@inheritdoc:description}'
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn('entity documentation');

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => 'action documentation. entity documentation'
            ],
            $this->context->getResult()
        );
    }

    public function testPrimaryResourceDocumentationLoadedByResourceDocProvider()
    {
        $entityClass = TestEntity::class;
        $targetAction = 'get_list';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $singularEntityDescription = 'some entity';
        $pluralEntityDescription = 'some entities';
        $resourceDocumentation = 'Get some entity';

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDescription')
            ->with($entityClass)
            ->willReturn($singularEntityDescription);
        $this->entityDocProvider->expects(self::once())
            ->method('getEntityPluralDescription')
            ->with($entityClass)
            ->willReturn($pluralEntityDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getResourceDocumentation')
            ->with($targetAction, $singularEntityDescription, $pluralEntityDescription)
            ->willReturn($resourceDocumentation);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $resourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationLoadedByResourceDocProvider()
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Get test association';

        $this->entityDocProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, false)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    public function testSubresourceDocumentationLoadedByResourceDocProviderForCollectionResource()
    {
        $parentEntityClass = TestEntity::class;
        $associationName = 'testAssociation';
        $targetAction = 'get_subresource';
        $config = [
            'exclusion_policy' => 'all'
        ];
        $associationDescription = 'test association';
        $subresourceDocumentation = 'Get test association';

        $this->entityDocProvider->expects(self::once())
            ->method('humanizeAssociationName')
            ->with($associationName)
            ->willReturn($associationDescription);
        $this->resourceDocProvider->expects(self::once())
            ->method('getSubresourceDocumentation')
            ->with($targetAction, $associationDescription, true)
            ->willReturn($subresourceDocumentation);

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setAssociationName($associationName);
        $this->context->setTargetAction($targetAction);
        $this->context->setIsCollection(true);
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $subresourceDocumentation
            ],
            $this->context->getResult()
        );
    }

    /**
     * @dataProvider preventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedTextProvider
     */
    public function testPreventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedText(
        $mainText,
        $inheritDocText,
        $expectedText
    ) {
        $entityClass = TestEntity::class;
        $config = [
            'exclusion_policy' => 'all',
            'documentation'    => $mainText
        ];

        $this->entityDocProvider->expects(self::once())
            ->method('getEntityDocumentation')
            ->with($entityClass)
            ->willReturn($inheritDocText);

        $this->context->setClassName($entityClass);
        $this->context->setTargetAction('get_list');
        $this->context->setResult($this->createConfigObject($config));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy'       => 'all',
                'identifier_description' => self::ID_DESCRIPTION,
                'documentation'          => $expectedText
            ],
            $this->context->getResult()
        );
    }

    public function preventingDoubleParagraphTagWhenInheritDocPlaceholderIsReplacedWithInheritedTextProvider()
    {
        return [
            'no paragraph tag'                                   => [
                'pre {@inheritdoc} post',
                'injection',
                'pre injection post'
            ],
            'null in inheritdoc text'                            => [
                'pre {@inheritdoc} post',
                null,
                'pre  post'
            ],
            'paragraph tag in main text'                         => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                'injection',
                '<p>pre</p><p>injection</p><p>post</p>'
            ],
            'paragraph tag in inheritdoc text'                   => [
                'pre {@inheritdoc} post',
                '<p>injection</p>',
                'pre injection post'
            ],
            'paragraph tag in both main and inheritdoc texts'    => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p>',
                '<p>pre</p><p>injection</p><p>post</p>'
            ],
            'several paragraph tags in inheritdoc text'          => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p><p>text</p>',
                '<p>pre</p><p>injection</p><p>text</p><p>post</p>'
            ],
            'paragraph tag in begin of inheritdoc text'          => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>injection</p><b>text</b>',
                '<p>pre</p><p>injection</p><b>text</b><p>post</p>'
            ],
            'paragraph tag in end of inheritdoc text'            => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<b>injection</b><p>text</p>',
                '<p>pre</p><b>injection</b><p>text</p><p>post</p>'
            ],
            'paragraph tag in middle of inheritdoc text'         => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<b>some</b><p>injection</p><b>text</b>',
                '<p>pre</p><b>some</b><p>injection</p><b>text</b><p>post</p>'
            ],
            'paragraph tags in begin and end of inheritdoc text' => [
                '<p>pre</p><p>{@inheritdoc}</p><p>post</p>',
                '<p>some</p><b>injection</b><p>text</p>',
                '<p>pre</p><p>some</p><b>injection</b><p>text</p><p>post</p>'
            ]
        ];
    }
}
