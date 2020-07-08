<?php

namespace Oro\Bundle\SegmentBundle\Tests\Unit\Query;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityBundle\Configuration\EntityConfigurationProvider;
use Oro\Bundle\EntityBundle\Provider\ConfigVirtualFieldProvider;
use Oro\Bundle\EntityBundle\Provider\EntityHierarchyProviderInterface;
use Oro\Bundle\FilterBundle\Filter\FilterInterface;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\FilterBundle\Filter\StringFilter;
use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\TextFilterType;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Manager;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\RestrictionBuilder;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Oro\Bundle\SegmentBundle\Query\DynamicSegmentQueryBuilder;
use Oro\Bundle\SegmentBundle\Query\SegmentQueryConverter;
use Oro\Bundle\SegmentBundle\Query\SegmentQueryConverterFactory;
use Oro\Bundle\SegmentBundle\Tests\Unit\SegmentDefinitionTestCase;
use Oro\Component\DependencyInjection\ServiceLink;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DynamicSegmentQueryBuilderTest extends SegmentDefinitionTestCase
{
    /** @var FormFactoryInterface */
    private $formFactory;

    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())
            ->method('trans')
            ->willReturnArgument(0);

        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtensions([
                new PreloadedExtension(
                    [
                        'oro_type_text_filter' => new TextFilterType($translator),
                        'oro_type_filter'      => new FilterType($translator)
                    ],
                    []
                ),
                new CsrfExtension(
                    $this->createMock(CsrfTokenManagerInterface::class)
                )
            ])
            ->getFormFactory();
    }

    public function testBuild()
    {
        $segment = $this->getSegment();
        $segment->setType(new SegmentType(SegmentType::TYPE_DYNAMIC));

        $doctrine = $this->getDoctrine(
            [self::TEST_ENTITY => ['username' => 'string', 'email' => 'string']],
            [self::TEST_ENTITY => [self::TEST_IDENTIFIER_NAME]]
        );
        $builder = $this->getQueryBuilder($doctrine);
        /** @var \PHPUnit\Framework\MockObject\MockObject $em */
        $em = $doctrine->getManagerForClass(self::TEST_ENTITY);
        $qb = new QueryBuilder($em);
        $this->mockConnection($em);
        $em->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $em->expects($this->any())
            ->method('getExpressionBuilder')
            ->willReturn(new Expr());
        $em->expects($this->any())
            ->method('createQuery')
            ->willReturn(new Query($em));

        $builder->build($segment);

        $result = $qb->getDQL();
        $counter = 0;
        $result = preg_replace_callback(
            '/(:[a-z]+)(\d+)/',
            function ($matches) use (&$counter) {
                return $matches[1] . (++$counter);
            },
            $result
        );
        $result = preg_replace('/(ts)(\d+)/', 't1', $result);
        $this->assertSame(
            'SELECT t1.userName, t1.id FROM AcmeBundle:UserEntity t1 WHERE t1.email LIKE :string1',
            $result
        );
    }

    public function testBuildExtended()
    {
        $segment = $this->getSegment(
            false,
            [
                'columns'          => [
                    [
                        'name'  => 'id',
                        'label' => 'Id'
                    ],
                    [
                        'name'    => 'userName',
                        'label'   => 'User name',
                        'func'    => null,
                        'sorting' => 'ASC'
                    ]
                ],
                'grouping_columns' => [['name' => 'id']],
                'filters'          => [
                    [
                        'columnName' => 'address+AcmeBundle:Address::zip',
                        'criterion'  => [
                            'filter' => 'string',
                            'data'   => [
                                'type'  => 1,
                                'value' => 'zip_code'
                            ]
                        ]
                    ],
                    'AND',
                    [
                        'columnName' => 'status+AcmeBundle:Status::code',
                        'criterion'  => [
                            'filter' => 'string',
                            'data'   => [
                                'type'  => 1,
                                'value' => 'code'
                            ]
                        ]
                    ]
                ]
            ]
        );
        $segment->setType(new SegmentType(SegmentType::TYPE_DYNAMIC));

        $doctrine = $this->getDoctrine(
            [
                self::TEST_ENTITY    => [
                    'username' => 'string',
                    'email'    => 'string',
                    'address'  => ['id'],
                    'status'   => ['id']
                ],
                'AcmeBundle:Address' => ['zip' => 'string'],
                'AcmeBundle:Status'  => ['code' => 'string']
            ],
            [self::TEST_ENTITY => [self::TEST_IDENTIFIER_NAME]]
        );
        $builder = $this->getQueryBuilder($doctrine);
        /** @var \PHPUnit\Framework\MockObject\MockObject $em */
        $em = $doctrine->getManagerForClass(self::TEST_ENTITY);
        $qb = new QueryBuilder($em);
        $this->mockConnection($em);
        $em->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($qb);
        $em->expects($this->any())
            ->method('getExpressionBuilder')
            ->willReturn(new Expr());
        $em->expects($this->any())
            ->method('createQuery')
            ->willReturn(new Query($em));

        $builder->build($segment);

        $this->assertEmpty($qb->getDQLPart('groupBy'));
        $this->assertNotEmpty($qb->getDQLPart('orderBy'));
        $this->assertNotEmpty($qb->getDQLPart('join'));
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject $doctrine
     *
     * @return DynamicSegmentQueryBuilder
     */
    private function getQueryBuilder($doctrine = null)
    {
        $manager = $this->createMock(Manager::class);
        $manager->expects($this->any())
            ->method('createFilter')
            ->willReturnCallback(function ($name, $params) {
                return $this->createFilter($name, $params);
            });

        $entityHierarchyProvider = $this->createMock(EntityHierarchyProviderInterface::class);
        $entityHierarchyProvider
            ->expects($this->any())
            ->method('getHierarchy')
            ->willReturn([]);

        $entityConfigurationProvider = $this->createMock(EntityConfigurationProvider::class);
        $entityConfigurationProvider->expects(self::any())
            ->method('getConfiguration')
            ->willReturn([]);
        $virtualFieldProvider = new ConfigVirtualFieldProvider(
            $entityHierarchyProvider,
            $entityConfigurationProvider
        );

        $doctrine = $doctrine ?? $this->getDoctrine();

        $segmentQueryConverterFactory = $this->createMock(SegmentQueryConverterFactory::class);
        /** @var ConfigManager $configManager */
        $configManager = $this->createMock(ConfigManager::class);

        $segmentQueryConverterFactory->expects($this->once())
            ->method('createInstance')
            ->willReturn(new SegmentQueryConverter(
                $manager,
                $virtualFieldProvider,
                $doctrine,
                new RestrictionBuilder($manager, $configManager)
            ));

        $serviceLink = $this->createMock(ServiceLink::class);
        $serviceLink->expects($this->once())
            ->method('getService')
            ->willReturn($segmentQueryConverterFactory);

        return new DynamicSegmentQueryBuilder($serviceLink, $doctrine);
    }


    /**
     * Creates a new instance of a filter based on a configuration
     * of a filter registered in this manager with the given name
     *
     * @param string $name   A filter name
     * @param array  $params An additional parameters of a new filter
     *
     * @return FilterInterface
     * @throws \Exception
     */
    public function createFilter($name, array $params = null)
    {
        $defaultParams = [
            'type' => $name
        ];
        if ($params !== null && !empty($params)) {
            $params = array_merge($defaultParams, $params);
        }

        switch ($name) {
            case 'string':
                $filter = new StringFilter($this->formFactory, new FilterUtility());
                break;
            default:
                throw new \Exception(sprintf('Not implemented in this test filter: "%s" . ', $name));
        }
        $filter->init($name, $params);

        return $filter;
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject $em
     */
    private function mockConnection($em)
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(null);
        $em->expects($this->any())
            ->method('getConnection')
            ->willReturn($connection);
    }
}
