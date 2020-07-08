<?php

namespace Oro\Bundle\SyncBundle\Tests\Unit\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\SyncBundle\Content\DoctrineTagGenerator;
use Oro\Bundle\SyncBundle\Tests\Unit\Content\Stub\EntityStub;
use Oro\Bundle\SyncBundle\Tests\Unit\Content\Stub\NewEntityStub;

class DoctrineTagGeneratorTest extends \PHPUnit\Framework\TestCase
{
    const TEST_ENTITY_NAME = 'Oro\Bundle\SyncBundle\Tests\Unit\Content\Stub\EntityStub';
    const TEST_ENTITY_ALIAS = 'OroSyncBundle:EntityStub';
    const TEST_NEW_ENTITY_NAME = 'Oro\Bundle\SyncBundle\Tests\Unit\Content\Stub\NewEntityStub';
    const TEST_ASSOCIATION_FIELD = 'testField';

    /** @var  DoctrineTagGenerator */
    protected $generator;

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityManager */
    protected $em;

    /** @var \PHPUnit\Framework\MockObject\MockObject|UnitOfWork */
    protected $uow;

    protected function setUp(): void
    {
        $this->em  = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $this->uow = $this->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()->getMock();

        $this->em->expects($this->any())
            ->method('getUnitOfWork')
            ->will($this->returnValue($this->uow));

        /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject $doctrine */
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->any())
            ->method('getManagerForClass')
            ->willReturnCallback(
                function ($class) {
                    $allowedClassNames = [self::TEST_ENTITY_NAME, self::TEST_ENTITY_ALIAS, self::TEST_NEW_ENTITY_NAME];
                    if (in_array($class, $allowedClassNames, true)) {
                        return $this->em;
                    }

                    return null;
                }
            );

        /** @var EntityClassResolver|\PHPUnit\Framework\MockObject\MockObject $entityClassResolver */
        $entityClassResolver = $this->createMock(EntityClassResolver::class);
        $entityClassResolver->expects($this->any())
            ->method('getEntityClass')
            ->willReturnMap([
                [self::TEST_ENTITY_ALIAS, self::TEST_ENTITY_NAME],
                [self::TEST_ENTITY_NAME, self::TEST_ENTITY_NAME],
                [self::TEST_NEW_ENTITY_NAME, self::TEST_NEW_ENTITY_NAME],
            ]);

        $this->generator = new DoctrineTagGenerator($doctrine, $entityClassResolver);
    }

    protected function tearDown(): void
    {
        unset($this->em, $this->resolver, $this->generator);
    }

    /**
     * @dataProvider supportsDataProvider
     *
     * @param mixed $data
     * @param bool  $expectedResult
     */
    public function testSupports($data, $expectedResult)
    {
        $this->assertSame($expectedResult, $this->generator->supports($data));
    }

    /**
     * @return array
     */
    public function supportsDataProvider()
    {
        return [
            'real entity object given'           => [new EntityStub(), true],
            'real entity class name given'       => [self::TEST_ENTITY_NAME, true],
            'form instance with real data given' => [$this->getFormMock(new EntityStub()), true],
            'array given'                        => [['someKey' => 'test'], false],
            'some string given'                  => ['testString', false],
            'form with array given'              => [$this->getFormMock(['someKey' => 'test']), false],
        ];
    }

    /**
     * @dataProvider generateDataProvider
     *
     * @param mixed $data
     * @param bool  $includeCollectionTag
     * @param int   $expectedCount
     * @param bool  $isManaged
     */
    public function testGenerate($data, $includeCollectionTag, $expectedCount, $isManaged = false)
    {
        // only once if it's object
        $this->uow->expects($this->exactly(is_object($data) ? 1 : 0))->method('getEntityState')
            ->will(
                $this->returnCallback(
                    function ($object) use ($isManaged) {
                        return $isManaged ? UnitOfWork::STATE_MANAGED : UnitOfWork::STATE_NEW;
                    }
                )
            );
        $this->uow->expects($this->exactly((int)$isManaged))->method('getEntityIdentifier')
            ->will($this->returnValue(['someIdentifierValue']));

        $result = $this->generator->generate($data, $includeCollectionTag);
        $this->assertCount($expectedCount, $result);
    }

    /**
     * @return array
     */
    public function generateDataProvider()
    {
        return [
            'Should not generate any tags for new entity'                           => [
                new NewEntityStub(),
                false,
                0
            ],
            'Should not generate only collection tag for new entity'                => [
                new NewEntityStub(),
                true,
                1
            ],
            'Should generate one tag for managed entity'                            => [
                new EntityStub(),
                false,
                1,
                true
            ],
            'Should generate two tag for managed entity when collection asked'      => [
                new EntityStub(),
                true,
                2,
                true
            ],
            'Should not generate tag when data taken from string'                   => [
                self::TEST_ENTITY_NAME,
                false,
                0
            ],
            'Should generate collection tag when data taken from string'            => [
                self::TEST_ENTITY_NAME,
                true,
                1
            ],
            'Should take data from form and return tags for managed entity'         => [
                $this->getFormMock(
                    new EntityStub()
                ),
                true,
                2,
                true
            ],
            'Should take data from form and generate collection tag for new entity' => [
                $this->getFormMock(
                    new NewEntityStub()
                ),
                true,
                1,
                false
            ],
        ];
    }

    /**
     * @dataProvider generateFromAliasDataProvider
     *
     * @param string $data
     * @param array $expectedResult
     */
    public function testGenerateFromAlias($data, $expectedResult)
    {
        $configurationMock = $this->createMock(Configuration::class);
        $configurationMock->method('getEntityNamespace')
            ->with('OroSyncBundle')
            ->willReturn('Oro\Bundle\SyncBundle\Tests\Unit\Content\Stub');

        $this->em->method('getConfiguration')
            ->willReturn($configurationMock);

        $result = $this->generator->generate($data, true);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function generateFromAliasDataProvider()
    {
        return [
            'generate tag from fqcn' => [
                self::TEST_ENTITY_NAME,
                ['Oro_Bundle_SyncBundle_Tests_Unit_Content_Stub_EntityStub_type_collection'],
            ],
            'should generate same tag as for fqcn' => [
                self::TEST_ENTITY_ALIAS,
                ['Oro_Bundle_SyncBundle_Tests_Unit_Content_Stub_EntityStub_type_collection'],
            ],
        ];
    }

    /**
     * @dataProvider collectNestingDataDataProvider
     *
     * @param array $associations
     * @param array $mappings
     * @param int   $expectedCount
     */
    public function testCollectNestingData($associations, $mappings, $expectedCount)
    {
        $testData   = new EntityStub();
        $reflection = new \ReflectionMethod($this->generator, 'collectNestedDataTags');
        $reflection->setAccessible(true);
        $this->uow->expects($this->any())->method('getEntityIdentifier')
            ->will($this->returnValue(['someIdentifierValue']));

        $metadata = new ClassMetadata(self::TEST_ENTITY_NAME);
        $metadata->associationMappings = $mappings;
        foreach ($associations as $name => $dataValue) {
            $field = $this->getMockBuilder('\ReflectionProperty')
                ->disableOriginalConstructor()->getMock();
            $field->expects($this->once())->method('getValue')->with($testData)
                ->will($this->returnValue($dataValue));
            $metadata->reflFields[$name] = $field;
        }

        $result = $reflection->invoke($this->generator, $testData, $metadata);

        $this->assertIsArray($result, 'Should always return array');
        $this->assertCount($expectedCount, $result, 'Should not generate collection tag for associations');
    }

    /**
     * @return array
     */
    public function collectNestingDataDataProvider()
    {
        $entityManagerMock = $this->getMockBuilder('Doctrine\ORM\EntityManagerInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $classMetadataMock = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();
        return [
            'should not return any data when no association on entity' => [[], [], 0],
            'should collect one to one associations' => [
                [self::TEST_ASSOCIATION_FIELD => new EntityStub()],
                [self::TEST_ASSOCIATION_FIELD => ['type' => ClassMetadata::ONE_TO_ONE]],
                1
            ],
            'should collect all collection associations using persistent collection' => [
                [
                    self::TEST_ASSOCIATION_FIELD => new PersistentCollection(
                        $entityManagerMock,
                        $classMetadataMock,
                        new ArrayCollection(
                            [
                                new EntityStub(),
                                new EntityStub()
                            ]
                        )
                    )
                ],
                [self::TEST_ASSOCIATION_FIELD => ['type' => ClassMetadata::ONE_TO_MANY]],
                2
            ],
            'should collect all collection associations using array collection' => [
                [
                    self::TEST_ASSOCIATION_FIELD => new ArrayCollection(
                        [
                            new EntityStub(),
                            new EntityStub()
                        ]
                    )
                ],
                [self::TEST_ASSOCIATION_FIELD => ['type' => ClassMetadata::ONE_TO_MANY]],
                2
            ],
            'should process all associated values using persistent collection' => [
                [
                    self::TEST_ASSOCIATION_FIELD . '_1' => new PersistentCollection(
                        $entityManagerMock,
                        $classMetadataMock,
                        new ArrayCollection(
                            [
                                new EntityStub(),
                                new EntityStub()
                            ]
                        )
                    ),
                    self::TEST_ASSOCIATION_FIELD . '_2' => new EntityStub()
                ],
                [
                    self::TEST_ASSOCIATION_FIELD . '_1' => ['type' => ClassMetadata::ONE_TO_MANY],
                    self::TEST_ASSOCIATION_FIELD . '_2' => ['type' => ClassMetadata::ONE_TO_ONE]
                ],
                3
            ],
            'should process all associated values using array collection' => [
                [
                    self::TEST_ASSOCIATION_FIELD . '_1' => new ArrayCollection(
                        [
                            new EntityStub(),
                            new EntityStub()
                        ]
                    ),
                    self::TEST_ASSOCIATION_FIELD . '_2' => new EntityStub()
                ],
                [
                    self::TEST_ASSOCIATION_FIELD . '_1' => ['type' => ClassMetadata::ONE_TO_MANY],
                    self::TEST_ASSOCIATION_FIELD . '_2' => ['type' => ClassMetadata::ONE_TO_ONE]
                ],
                3
            ]
        ];
    }

    /**
     * @param mixed $data
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function getFormMock($data)
    {
        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $form->expects($this->any())->method('getData')
            ->will($this->returnValue($data));

        return $form;
    }
}
