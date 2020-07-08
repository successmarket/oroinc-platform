<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Datagrid\Extension\Pager;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Extension\Toolbar\ToolbarExtension;
use Oro\Bundle\SearchBundle\Datagrid\Datasource\SearchDatasource;
use Oro\Bundle\SearchBundle\Datagrid\Extension\Pager\IndexerPager;
use Oro\Bundle\SearchBundle\Datagrid\Extension\Pager\SearchPagerExtension;
use Oro\Bundle\SearchBundle\Query\SearchQueryInterface;

class SearchPagerExtensionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var  \PHPUnit\Framework\MockObject\MockObject | DatagridConfiguration
     */
    protected $datagridConfig;

    /**
     * @var  \PHPUnit\Framework\MockObject\MockObject | IndexerPager
     */
    protected $pager;

    /**
     * @var SearchPagerExtension
     */
    protected $pagerExtension;

    protected function setUp(): void
    {
        $this->datagridConfig = $this->createMock(DatagridConfiguration::class);
        $this->pager = $this->createMock(IndexerPager::class);

        $this->pagerExtension = new SearchPagerExtension($this->pager);

        $parameterBag = new ParameterBag();
        $this->pagerExtension->setParameters($parameterBag);
    }

    protected function tearDown(): void
    {
        unset($this->pagerExtension);
    }

    /**
     * @dataProvider providerTestIsApplicable
     *
     * @param string $dataSourceType
     * @param bool $expectedResult
     */
    public function testIsApplicable($dataSourceType, $expectedResult)
    {
        $this->datagridConfig->expects($this->once())->method('getDatasourceType')->willReturn($dataSourceType);
        $this->assertEquals($expectedResult, $this->pagerExtension->isApplicable($this->datagridConfig));
    }

    /**
     * @return array
     */
    public function providerTestIsApplicable()
    {
        return [
            'Check applicable data source type' => [
                'dataSourceType' => 'search',
                'expectedResult' => true
            ],
            'Check inapplicable data source type' => [
                'dataSourceType' => 'orm',
                'expectedResult' => false
            ]
        ];
    }

    /**
     * @dataProvider providerTestVisitDataSource
     *
     * @param int $perPage
     * @param bool $onePageEnable
     */
    public function testVisitDataSource($perPage, $onePageEnable)
    {
        $dataSource = $this->createMock(SearchDatasource::class);
        $searchQuery = $this->createMock(SearchQueryInterface::class);

        $this->datagridConfig->expects($this->exactly(2))
            ->method('offsetGetByPath')
            ->with($this->logicalOr(
                $this->equalTo(ToolbarExtension::PAGER_DEFAULT_PER_PAGE_OPTION_PATH),
                $this->equalTo(ToolbarExtension::PAGER_ONE_PAGE_OPTION_PATH)
            ))
            ->will($this->returnCallback(function ($key) use ($perPage, $onePageEnable) {
                if ($key === ToolbarExtension::PAGER_DEFAULT_PER_PAGE_OPTION_PATH) {
                    return $perPage;
                }

                if ($key === ToolbarExtension::PAGER_ONE_PAGE_OPTION_PATH) {
                    return $onePageEnable;
                }
            }));

        $dataSource->expects($this->once())->method('getSearchQuery')->willReturn($searchQuery);
        $this->pager->expects($this->once())->method('setQuery')->with($searchQuery);

        if ($onePageEnable) {
            $this->pager->expects($this->once())->method('setMaxPerPage')->with(1000);
        } else {
            $this->pager->expects($this->once())->method('setMaxPerPage')->with(20);
        }

        $this->pager->expects($this->once())->method('init');

        $this->pagerExtension->visitDatasource($this->datagridConfig, $dataSource);
    }

    /**
     * @return array
     */
    public function providerTestVisitDataSource()
    {
        return [
            'Enabled One page option for DataGrid' => [
                'perPage' => 20,
                'onePageEnable' => true
            ],
            'Disabled One page option for DataGrid' => [
                'perPage' => 20,
                'onePageEnable' => false
            ]
        ];
    }

    /**
     * @dataProvider datagridPagerParamsDataProvider
     *
     * @param array $parameters
     * @param integer|null $expectedPageSize
     * @param integer|null $expectPage
     */
    public function testProcessConfigs(array $parameters, $expectedPageSize, $expectPage)
    {
        $config = [
            'options' => [
                'toolbarOptions' => [
                    'pageSize' => [
                        'default_per_page' => 25,
                        'items' => [10, 25, 50, 100]
                    ]
                ]
            ]
        ];

        $datagridConfiguration = DatagridConfiguration::create($config);
        $parameterBag = new ParameterBag($parameters);

        $this->pagerExtension->setParameters($parameterBag);
        $this->pagerExtension->processConfigs($datagridConfiguration);

        $pagerParameters = $parameterBag->get('_pager');
        $this->assertEquals($expectedPageSize, $pagerParameters['_per_page'] ?? null);
        $this->assertEquals($expectPage, $pagerParameters['_page'] ?? null);
    }

    /**
     * @return array
     */
    public function datagridPagerParamsDataProvider(): array
    {
        return [
            'empty params' => [
                'parameters' => [
                    '_pager' => [
                        '_page' => '',
                        '_per_page' => '',
                    ]
                ],
                'expectedPageSize' => 25,
                'expectPage' => 1,
            ],
            'wrong params' => [
                'parameters' => [
                    '_pager' => [
                        '_page' => '2a',
                        '_per_page' => 1,
                    ]
                ],
                'expectedPageSize' => 25,
                'expectPage' => 1,
            ],
            'correct params' => [
                'parameters' => [
                    '_pager' => [
                        '_page' => 2,
                        '_per_page' => 10,
                    ]
                ],
                'expectedPageSize' => 10,
                'expectPage' => 2,
            ],
            'params does not exists' => [
                'parameters' => [
                    '_pager' => []
                ],
                'expectedPageSize' => 25,
                'expectPage' => 1,
            ],
            'without pager parameters' => [
                'parameters' => [],
                'expectedPageSize' => null,
                'expectPage' => null,
            ],
        ];
    }
}
