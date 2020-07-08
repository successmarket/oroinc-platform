<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Action;

use Oro\Bundle\ActionBundle\Action\CreateDate;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyPath;

class CreateDateTest extends \PHPUnit\Framework\TestCase
{
    const TIMEZONE = 'Europe/London';

    /**
     * @var CreateDate
     */
    protected $action;

    /**
     * @var ContextAccessor
     */
    protected $contextAccessor;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LocaleSettings
     */
    protected $localeSettings;

    protected function setUp(): void
    {
        $this->contextAccessor = new ContextAccessor();

        $this->localeSettings = $this->getMockBuilder('Oro\Bundle\LocaleBundle\Model\LocaleSettings')
            ->disableOriginalConstructor()
            ->getMock();
        $this->localeSettings->expects($this->any())
            ->method('getTimeZone')
            ->will($this->returnValue(self::TIMEZONE));

        $this->action = new CreateDate($this->contextAccessor, $this->localeSettings);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action->setDispatcher($dispatcher);
    }

    protected function tearDown(): void
    {
        unset($this->contextAccessor, $this->localeSettings, $this->action);
    }

    public function testInitializeExceptionInvalidTime()
    {
        $this->expectException(\Oro\Component\Action\Exception\InvalidParameterException::class);
        $this->expectExceptionMessage('Option "date" must be a string, boolean given.');

        $this->action->initialize(array('attribute' => new PropertyPath('test_attribute'), 'date' => true));
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options, $expectedResult = null)
    {
        $context = new ItemStub(array());
        $attributeName = (string)$options['attribute'];
        $this->action->initialize($options);
        $this->action->execute($context);
        $this->assertNotNull($context->$attributeName);
        $this->assertInstanceOf('DateTime', $context->$attributeName);

        if ($expectedResult) {
            $this->assertEquals($expectedResult, $context->$attributeName);
        }
    }

    /**
     * @return array
     */
    public function executeDataProvider()
    {
        return array(
            'without_date' => array(
                'options' => array(
                    'attribute' => new PropertyPath('test_attribute'),
                ),
            ),
            'with_date' => array(
                'options' => array(
                    'attribute' => new PropertyPath('test_attribute'),
                    'date'      => '2014-01-01',
                ),
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('UTC'))
            ),
            'with_datetime' => array(
                'options' => array(
                    'attribute' => new PropertyPath('test_attribute'),
                    'date'      => '2014-01-01 12:12:12',
                ),
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('UTC'))
            ),
        );
    }
}
