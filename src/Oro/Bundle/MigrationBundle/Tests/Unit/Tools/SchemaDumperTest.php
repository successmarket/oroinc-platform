<?php

namespace Oro\Bundle\MigrationBundle\Tests\Unit\Tools;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Tools\SchemaDumper;

class SchemaDumperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SchemaDumper
     */
    protected $schemaDumper;

    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $twig;

    protected function setUp(): void
    {
        $this->twig = $this->getMockBuilder('\Twig_Environment')->disableOriginalConstructor()->getMock();
        $this->schema = new Schema();
        $this->schemaDumper = new SchemaDumper($this->twig);

        $this->schemaDumper->acceptSchema($this->schema);
    }

    /**
     * @dataProvider dumpDataProvider
     * @param array|null $allowedTables
     * @param string|null $namespace
     * @param string|null $expectedNamespace
     * @param string $className
     * @param string $version
     * @param array $extendedOptions
     */
    public function testDump(
        $allowedTables,
        $namespace,
        $expectedNamespace,
        $className,
        $version,
        $extendedOptions
    ) {
        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                SchemaDumper::SCHEMA_TEMPLATE,
                [
                    'schema' => $this->schema,
                    'allowedTables' => $allowedTables,
                    'namespace' => $expectedNamespace,
                    'className' => $className,
                    'version' => $version,
                    'extendedOptions' => $extendedOptions
                ]
            )
            ->will($this->returnValue('TEST'));

        $this->assertEquals(
            'TEST',
            $this->schemaDumper->dump($allowedTables, $namespace, $className, $version, $extendedOptions)
        );
    }

    public function dumpDataProvider()
    {
        return array(
            array(null, null, null, null, null, null),
            array(
                array('test' => true),
                'Acme\DemoBundle\Entity',
                'Acme\DemoBundle',
                'DemoBundleInstaller',
                'v1_1',
                array('test' => array('id' => array('test' => true)))
            )
        );
    }
}
