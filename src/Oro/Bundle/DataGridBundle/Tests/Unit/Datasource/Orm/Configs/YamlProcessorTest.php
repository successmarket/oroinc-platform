<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Datasource\Orm\Configs;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datasource\Orm\Configs\YamlProcessor;

class YamlProcessorTest extends \PHPUnit\Framework\TestCase
{
    /** @var YamlProcessor */
    protected $processor;

    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    protected $registry;

    /** @var EntityManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $em;

    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMock();
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new YamlProcessor($this->registry);
    }

    public function testProcessQuery()
    {
        $entity1 = 'EntityTest1';
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entity1)
            ->willReturn($this->em);
        $qb = new QueryBuilder($this->em);

        $this->em
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $configs = [
            'type' => 'orm',
            'query' => [
                'select' => [
                    't1.id',
                    't2.id as t2_id'
                ],
                'from' => [['table' => $entity1, 'alias' => 't1']],
                'join' => [
                    'left' => [['join' => 't1.test2', 'alias' => 't2']]
                ],
                'where' => [
                    'and' => ['t1.type = someType']
                ]
            ]
        ];
        $queryBuilder = $this->processor->processQuery($configs);

        $this->assertSame($queryBuilder, $qb);
        $this->assertEquals(
            'SELECT t1.id, t2.id as t2_id FROM EntityTest1 t1 LEFT JOIN t1.test2 t2 WHERE t1.type = someType',
            $queryBuilder->getDQL()
        );
    }

    public function testProcessQueryWithService()
    {
        $qb = new QueryBuilder($this->em);

        $configs = [
            'type' => 'orm',
            'query_builder' => $qb,
        ];

        $queryBuilder = $this->processor->processQuery($configs);
        $this->assertSame($queryBuilder, $qb);
    }

    public function testNoQueryAndRepositoryConfigsShouldThrowException()
    {
        $this->expectException(\Oro\Bundle\DataGridBundle\Exception\DatasourceException::class);
        $this->expectExceptionMessage(\sprintf(
            '%s expects to be configured with query or repository method',
            \Oro\Bundle\DataGridBundle\Datasource\Orm\Configs\YamlProcessor::class
        ));

        $configs      = [
            'type'  => 'orm',
        ];
        $this->processor->processQuery($configs);
    }

    public function testEntityRepositoryDoesNotHasMethodShouldThrowException()
    {
        $this->expectException(\Oro\Bundle\DataGridBundle\Exception\DatasourceException::class);
        $this->expectExceptionMessage('Doctrine\ORM\EntityRepository has no method notExistedMethod');

        $entity1 = 'EntityTest1';

        $configs = [
            'type' => 'orm',
            'entity' => $entity1,
            'repository_method' => 'notExistedMethod'
        ];
        $repo = new EntityRepository($this->em, new ClassMetadata($entity1));
        $this->registry->expects($this->once())
            ->method('getRepository')
            ->with($entity1)
            ->willReturn($repo);
        $this->processor->processQuery($configs);
    }

    public function testConfigMethodDoNotReturnQueryBuilderShouldThrowException()
    {
        $entity1 = 'EntityTest1';

        $configs = [
            'type'              => 'orm',
            'entity'            => $entity1,
            'repository_method' => 'methodNotReturnQB'
        ];
        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->setMethods(['methodNotReturnQB'])
            ->disableOriginalConstructor()
            ->getMock();
        $repo->expects($this->once())
            ->method('methodNotReturnQB')
            ->willReturn(null);

        $this->registry->expects($this->once())
            ->method('getRepository')
            ->with($entity1)
            ->willReturn($repo);

        $this->expectException(\Oro\Bundle\DataGridBundle\Exception\DatasourceException::class);
        $this->expectExceptionMessage(
            sprintf(
                '%s::methodNotReturnQB() must return an instance of Doctrine\ORM\QueryBuilder, %s given',
                get_class($repo),
                gettype(null)
            )
        );
        $this->processor->processQuery($configs);
    }

    public function testServicedDoNotReturnQueryBuilderShouldThrowException()
    {
        $qb = 'not-a-querybuilder';

        $configs = [
            'type' => 'orm',
            'query_builder' => $qb,
        ];

        $this->expectException(\Oro\Bundle\DataGridBundle\Exception\DatasourceException::class);
        $this->expectExceptionMessage(
            sprintf(
                '%s configured with service must return an instance of Doctrine\ORM\QueryBuilder, %s given',
                'Oro\Bundle\DataGridBundle\Datasource\Orm\Configs\YamlProcessor',
                gettype($qb)
            )
        );
        $this->processor->processQuery($configs);
    }

    public function testMergeCountAndBaseQueryConfigs()
    {
        $entity1 = 'EntityTest1';
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($entity1)
            ->willReturn($this->em);
        $qb = new QueryBuilder($this->em);

        $this->em
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $configs      = [
            'type'  => 'orm',
            'query' => [
                'select' => [
                    't1.id',
                    't2.id as t2_id'
                ],
                'from'   => [['table' => $entity1, 'alias' => 't1']],
                'join'   => [
                    'left' => [['join' => 't1.test2', 'alias' => 't2']]
                ],
                'where'  => [
                    'and' => ['t1.type = someType']
                ]
            ],
            'count_query' => [
                'select' => [
                    't1.id'
                ],
                'join'   => null,
            ]
        ];
        $queryBuilder = $this->processor->processCountQuery($configs);

        $this->assertSame($queryBuilder, $qb);
        $this->assertEquals(
            'SELECT t1.id FROM EntityTest1 t1 WHERE t1.type = someType',
            $queryBuilder->getDQL()
        );
    }
}
