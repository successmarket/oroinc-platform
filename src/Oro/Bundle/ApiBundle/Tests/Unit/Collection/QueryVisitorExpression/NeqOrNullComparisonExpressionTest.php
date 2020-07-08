<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Collection\QueryVisitorExpression;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ApiBundle\Collection\QueryExpressionVisitor;
use Oro\Bundle\ApiBundle\Collection\QueryVisitorExpression\NeqOrNullComparisonExpression;
use Oro\Bundle\ApiBundle\Model\Range;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\OrmRelatedTestCase;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;

class NeqOrNullComparisonExpressionTest extends OrmRelatedTestCase
{
    public function testWalkComparisonExpressionForNullValue()
    {
        $this->expectException(\Doctrine\ORM\Query\QueryException::class);
        $this->expectExceptionMessage('The value for "e.test" must not be NULL.');

        $expression = new NeqOrNullComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            $this->createMock(EntityClassResolver::class)
        );
        $field = 'e.test';
        $expr = 'LOWER(e.test)';
        $parameterName = 'test_1';
        $value = null;

        $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );
    }

    public function testWalkComparisonExpression()
    {
        $expression = new NeqOrNullComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            $this->createMock(EntityClassResolver::class)
        );
        $field = 'e.test';
        $expr = 'LOWER(e.test)';
        $parameterName = 'test_1';
        $value = 'text';

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        self::assertEquals(
            new Expr\Orx(
                [
                    new Expr\Func($expr . ' NOT IN', [':' . $parameterName]),
                    $expr . ' IS NULL'
                ]
            ),
            $result
        );
        self::assertEquals(
            [new Parameter($parameterName, $value)],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionForRangeValue()
    {
        $expression = new NeqOrNullComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups';
        $expr = 'LOWER(e.groups)';
        $parameterName = 'groups_1';
        $fromValue = 123;
        $toValue = 234;
        $value = new Range($fromValue, $toValue);

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT groups_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 = groups'
            . ' AND (groups_subquery1 BETWEEN :groups_1_from AND :groups_1_to)';

        self::assertEquals(
            new Expr\Orx(
                [
                    new Expr\Func('NOT', [new Expr\Func('EXISTS', [$expectedSubquery])]),
                    $expr . ' IS NULL'
                ]
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter('groups_1_from', $fromValue),
                new Parameter('groups_1_to', $toValue)
            ],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionForRangeValueWhenLastElementInPathIsField()
    {
        $expression = new NeqOrNullComparisonExpression();
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups.name';
        $expr = 'LOWER(e.groups.name)';
        $parameterName = 'groups_1';
        $fromValue = 123;
        $toValue = 234;
        $value = new Range($fromValue, $toValue);

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e')
            ->innerJoin('e.groups', 'groups');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e', 'groups']);
        $expressionVisitor->setQueryJoinMap(['groups' => 'groups']);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT groups_subquery1'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 = groups'
            . ' AND (groups_subquery1.name BETWEEN :groups_1_from AND :groups_1_to)';

        self::assertEquals(
            new Expr\Orx(
                [
                    new Expr\Func('NOT', [new Expr\Func('EXISTS', [$expectedSubquery])]),
                    $expr . ' IS NULL'
                ]
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter('groups_1_from', $fromValue),
                new Parameter('groups_1_to', $toValue)
            ],
            $expressionVisitor->getParameters()
        );
    }
}
