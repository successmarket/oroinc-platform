<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Extension\Pager;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Extension\Mode\ModeExtension;
use Oro\Bundle\DataGridBundle\Extension\Pager\Orm\Pager;
use Oro\Bundle\DataGridBundle\Extension\Pager\OrmPagerExtension;
use Oro\Bundle\DataGridBundle\Extension\Pager\PagerInterface;

class OrmPagerExtensionTest extends \PHPUnit\Framework\TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|Pager */
    private $pager;

    /** @var OrmPagerExtension */
    private $extension;

    protected function setUp(): void
    {
        $this->pager = $this->createMock(Pager::class);

        $this->extension = new OrmPagerExtension($this->pager);
    }

    /**
     * @param array $input
     * @param array $expected
     * @dataProvider setParametersDataProvider
     */
    public function testSetParameters(array $input, array $expected)
    {
        $this->extension->setParameters(new ParameterBag($input));
        $this->assertEquals($expected, $this->extension->getParameters()->all());
    }

    /**
     * @return array
     */
    public function setParametersDataProvider()
    {
        return [
            'empty' => [
                'input' => [],
                'expected' => [],
            ],
            'regular' => [
                'input' => [
                    PagerInterface::PAGER_ROOT_PARAM => [
                        PagerInterface::PAGE_PARAM => 1,
                        PagerInterface::PER_PAGE_PARAM => 25,
                    ]
                ],
                'expected' => [
                    PagerInterface::PAGER_ROOT_PARAM => [
                        PagerInterface::PAGE_PARAM => 1,
                        PagerInterface::PER_PAGE_PARAM => 25,
                    ]
                ]
            ],
            'minified' => [
                'input' => [
                    ParameterBag::MINIFIED_PARAMETERS => [
                        PagerInterface::MINIFIED_PAGE_PARAM => 1,
                        PagerInterface::MINIFIED_PER_PAGE_PARAM => 25,
                    ]
                ],
                'expected' => [
                    ParameterBag::MINIFIED_PARAMETERS => [
                        PagerInterface::MINIFIED_PAGE_PARAM => 1,
                        PagerInterface::MINIFIED_PER_PAGE_PARAM => 25,
                    ],
                    PagerInterface::PAGER_ROOT_PARAM => [
                        PagerInterface::PAGE_PARAM => 1,
                        PagerInterface::PER_PAGE_PARAM => 25,
                    ]
                ]
            ],
        ];
    }

    public function visitDatasourceNoRestrictionsDataProvider()
    {
        return [
            'regular grid' => [
                'config' => [],
                'page' => 1,
                'maxPerPage' => 10,
            ],
            'one page pagination' => [
                'config' => [
                    'options' => [
                        'toolbarOptions' => [
                            'pagination' => [
                                'onePage' => true
                            ]
                        ]
                    ]
                ],
                'page' => 0,
                'maxPerPage' => 1000,
            ],
            'client mode' => [
                'config' => [
                    'options' => [
                        'mode' => ModeExtension::MODE_CLIENT,
                    ]
                ],
                'page' => 0,
                'maxPerPage' => 1000,
            ],
        ];
    }

    /**
     * @param array $config
     * @param int $page
     * @param int $maxPerPage
     * @dataProvider visitDatasourceNoRestrictionsDataProvider
     */
    public function testVisitDatasourceNoPagerRestrictions(array $config, $page, $maxPerPage)
    {
        $configObject = DatagridConfiguration::create($config);
        $dataSource = $this->createMock(OrmDatasource::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $datagrid = $this->createMock(DatagridInterface::class);

        $dataSource->expects($this->once())
            ->method('getDatagrid')
            ->willReturn($datagrid);
        $dataSource->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($queryBuilder);
        $dataSource->expects($this->once())
            ->method('getCountQueryHints')
            ->willReturn([]);

        $this->pager->expects($this->once())
            ->method('setDatagrid')
            ->with($this->identicalTo($datagrid));
        $this->pager->expects($this->once())
            ->method('setPage')
            ->with($page);
        $this->pager->expects($this->once())
            ->method('setMaxPerPage')
            ->with($maxPerPage);

        $this->extension->setParameters(new ParameterBag());
        $this->extension->visitDatasource($configObject, $dataSource);
    }

    /**
     * @param null $count
     * @param bool $adjustTotalCount
     *
     * @dataProvider adjustedCountDataProvider
     */
    public function testVisitDatasourceWithAdjustedCount($count, $adjustTotalCount = false)
    {
        $configObject = DatagridConfiguration::create([]);
        $dataSource = $this->createMock(OrmDatasource::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $datagrid = $this->createMock(DatagridInterface::class);

        $dataSource->expects($this->once())
            ->method('getDatagrid')
            ->willReturn($datagrid);
        $dataSource->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($queryBuilder);
        $dataSource->expects($this->once())
            ->method('getCountQueryHints')
            ->willReturn([]);

        $this->pager->expects($this->once())
            ->method('setDatagrid')
            ->with($this->identicalTo($datagrid));
        if ($adjustTotalCount) {
            $this->pager->expects($this->once())
                ->method('adjustTotalCount')
                ->with($count);
        } else {
            $this->pager->expects($this->never())
                ->method('adjustTotalCount')
                ->with($count);
        }

        $parameters = [];
        if (null !== $count) {
            $parameters[PagerInterface::PAGER_ROOT_PARAM] = [PagerInterface::ADJUSTED_COUNT => $count];
        }
        $this->extension->setParameters(new ParameterBag($parameters));
        $this->extension->visitDatasource($configObject, $dataSource);
    }

    public function testHintCount()
    {
        $hints = ['HINT'];

        $configObject = DatagridConfiguration::create([]);
        $dataSource = $this->createMock(OrmDatasource::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $datagrid = $this->createMock(DatagridInterface::class);

        $dataSource->expects($this->once())
            ->method('getDatagrid')
            ->willReturn($datagrid);
        $dataSource->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($queryBuilder);
        $dataSource->expects($this->once())
            ->method('getCountQueryHints')
            ->willReturn($hints);

        $this->pager->expects($this->once())
            ->method('setDatagrid')
            ->with($this->identicalTo($datagrid));
        $this->pager->expects($this->once())
            ->method('setCountQueryHints')
            ->with($hints);

        $this->extension->setParameters(new ParameterBag([]));
        $this->extension->visitDatasource($configObject, $dataSource);
    }

    public function adjustedCountDataProvider()
    {
        return [
            'valid value' => [150, true],
            'no value' => [null],
            'not valid value(negative)' => [-100],
            'not valid value(string)' => ['test'],
            'not valid value(false)' => [false],
            'not valid value(true)' => [true]
        ];
    }
}
