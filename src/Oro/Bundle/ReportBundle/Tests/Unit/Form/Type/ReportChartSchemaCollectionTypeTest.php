<?php

namespace Oro\Bundle\ReportBundle\Tests\Unit\Form\Type;

use Oro\Bundle\ChartBundle\Model\ConfigProvider;
use Oro\Bundle\QueryDesignerBundle\Form\Type\FieldChoiceType;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Manager as QueryDesignerManager;
use Oro\Bundle\ReportBundle\Form\Type\ReportChartSchemaCollectionType;
use Oro\Bundle\ReportBundle\Form\Type\ReportChartSchemaType;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportChartSchemaCollectionTypeTest extends FormIntegrationTestCase
{
    /** @var ReportChartSchemaCollectionType */
    private $type;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $configProvider;

    protected function setUp(): void
    {
        $chartConfigs = [
            'line_chart' => [
                'data_schema' => [
                    [
                        'label'    => 'label',
                        'name'     => 'name',
                        'required' => false
                    ]
                ]
            ]
        ];

        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->configProvider->expects($this->any())
            ->method('getChartNames')
            ->willReturn(array_keys($chartConfigs));
        $this->configProvider->expects($this->any())
            ->method('getChartConfig')
            ->willReturnCallback(function ($name) use ($chartConfigs) {
                return $chartConfigs[$name];
            });

        $this->type = new ReportChartSchemaCollectionType($this->configProvider);

        parent::setUp();
    }

    public function testBuildForm()
    {
        $this->factory->create(ReportChartSchemaCollectionType::class, null, []);
    }

    /**
     * @return array
     */
    protected function getExtensions()
    {
        $manager = $this->createMock(QueryDesignerManager::class);
        $translator = $this->createMock(TranslatorInterface::class);

        return [
            new PreloadedExtension(
                [
                    ReportChartSchemaCollectionType::class => $this->type,
                    ReportChartSchemaType::class => new ReportChartSchemaType($manager),
                    FieldChoiceType::class => new FieldChoiceType($translator)
                ],
                []
            )
        ];
    }
}
