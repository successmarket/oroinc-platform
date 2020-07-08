<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Collection\QueryVisitorExpression;

use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ApiBundle\Collection\QueryExpressionVisitor;
use Oro\Bundle\ApiBundle\Collection\QueryVisitorExpression\AllMemberOfComparisonExpression;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\OrmRelatedTestCase;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;

class AllNotMemberOfComparisonExpressionTest extends OrmRelatedTestCase
{
    public function testWalkComparisonExpressionWhenAssociationIsNotJoined()
    {
        $expression = new AllMemberOfComparisonExpression(true);
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups';
        $expr = 'LOWER(e.groups)';
        $parameterName = 'groups_1';
        $value = [1, 2, 3];
        $expectedNumberOfRecordsParameterName = $parameterName . '_expected';

        $qb = new QueryBuilder($this->em);
        $qb
            ->select('e')
            ->from(Entity\User::class, 'e');

        $expressionVisitor->setQuery($qb);
        $expressionVisitor->setQueryAliases(['e']);
        $expressionVisitor->setQueryJoinMap([]);

        $result = $expression->walkComparisonExpression(
            $expressionVisitor,
            $field,
            $expr,
            $parameterName,
            $value
        );

        $expectedSubquery = 'SELECT COUNT(groups_subquery1)'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 MEMBER OF e.groups AND groups_subquery1 IN(:groups_1)';

        self::assertEquals(
            new Comparison(
                ':' . $expectedNumberOfRecordsParameterName,
                Comparison::EQ,
                '(' . $expectedSubquery . ')'
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter($parameterName, $value),
                new Parameter($expectedNumberOfRecordsParameterName, 0)
            ],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionForArrayValue()
    {
        $expression = new AllMemberOfComparisonExpression(true);
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups';
        $expr = 'LOWER(e.groups)';
        $parameterName = 'groups_1';
        $value = [1, 2, 3];
        $expectedNumberOfRecordsParameterName = $parameterName . '_expected';

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

        $expectedSubquery = 'SELECT COUNT(groups_subquery1)'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 MEMBER OF e.groups AND groups_subquery1 IN(:groups_1)';

        self::assertEquals(
            new Comparison(
                ':' . $expectedNumberOfRecordsParameterName,
                Comparison::EQ,
                '(' . $expectedSubquery . ')'
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter($parameterName, $value),
                new Parameter($expectedNumberOfRecordsParameterName, 0)
            ],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionForScalarValue()
    {
        $expression = new AllMemberOfComparisonExpression(true);
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups';
        $expr = 'LOWER(e.groups)';
        $parameterName = 'groups_1';
        $value = 123;
        $expectedNumberOfRecordsParameterName = $parameterName . '_expected';

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

        $expectedSubquery = 'SELECT COUNT(groups_subquery1)'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 MEMBER OF e.groups AND groups_subquery1 IN(:groups_1)';

        self::assertEquals(
            new Comparison(
                ':' . $expectedNumberOfRecordsParameterName,
                Comparison::EQ,
                '(' . $expectedSubquery . ')'
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter($parameterName, $value),
                new Parameter($expectedNumberOfRecordsParameterName, 0)
            ],
            $expressionVisitor->getParameters()
        );
    }

    public function testWalkComparisonExpressionWhenLastElementInPathIsField()
    {
        $expression = new AllMemberOfComparisonExpression(true);
        $expressionVisitor = new QueryExpressionVisitor(
            [],
            [],
            new EntityClassResolver($this->doctrine)
        );
        $field = 'e.groups.name';
        $expr = 'LOWER(e.groups.name)';
        $parameterName = 'groups_1';
        $value = 123;
        $expectedNumberOfRecordsParameterName = $parameterName . '_expected';

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

        $expectedSubquery = 'SELECT COUNT(groups_subquery1)'
            . ' FROM Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group groups_subquery1'
            . ' WHERE groups_subquery1 MEMBER OF e.groups AND groups_subquery1.name IN(:groups_1)';

        self::assertEquals(
            new Comparison(
                ':' . $expectedNumberOfRecordsParameterName,
                Comparison::EQ,
                '(' . $expectedSubquery . ')'
            ),
            $result
        );
        self::assertEquals(
            [
                new Parameter($parameterName, $value),
                new Parameter($expectedNumberOfRecordsParameterName, 0)
            ],
            $expressionVisitor->getParameters()
        );
    }
}
