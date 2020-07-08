<?php

namespace Oro\Bundle\ActivityListBundle\Tests\Unit\AccessRule;

use Oro\Bundle\ActivityListBundle\AccessRule\ActivityListAccessRule;
use Oro\Bundle\ActivityListBundle\Entity\ActivityList;
use Oro\Bundle\ActivityListBundle\Provider\ActivityListChainProvider;
use Oro\Bundle\SecurityBundle\AccessRule\Criteria;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Comparison;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\CompositeExpression;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\NullComparison;
use Oro\Bundle\SecurityBundle\AccessRule\Expr\Path;
use Oro\Bundle\SecurityBundle\ORM\Walker\AccessRuleWalker;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclConditionDataBuilderInterface;

class ActivityListAccessRuleTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_ACTIVITY_CLASS = 'Test\Entity';

    /** @var AclConditionDataBuilderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $builder;

    /** @var ActivityListChainProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $activityListProvider;

    /** @var ActivityListAccessRule */
    private $rule;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(AclConditionDataBuilderInterface::class);
        $this->activityListProvider = $this->createMock(ActivityListChainProvider::class);

        $this->rule = new ActivityListAccessRule($this->builder, $this->activityListProvider);
    }

    public function testIsApplicableWithoutActivityOwnerTableAlias()
    {
        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');

        $this->assertFalse($this->rule->isApplicable($criteria));
    }

    public function testIsApplicableWithActivityOwnerTableAlias()
    {
        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');
        $criteria->setOption(ActivityListAccessRule::ACTIVITY_OWNER_TABLE_ALIAS, 'test');

        $this->assertTrue($this->rule->isApplicable($criteria));
    }

    public function testProcessWithoutActivityOwnerTableAliasOption()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'The "activityListActivityOwnerTableAlias" option was not set to ActivityListAccessRule access rule.'
        );

        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');
        $this->rule->process($criteria);
    }

    public function testProcessWithEmptyActivityListProviders()
    {
        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');
        $criteria->setOption(ActivityListAccessRule::ACTIVITY_OWNER_TABLE_ALIAS, 'oa');

        $this->activityListProvider->expects($this->once())
            ->method('getSupportedActivities')
            ->willReturn([]);
        $this->activityListProvider->expects($this->never())
            ->method('getSupportedOwnerActivity');

        $this->rule->process($criteria);
        $this->assertEquals(
            new CompositeExpression(
                CompositeExpression::TYPE_OR,
                [
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        [
                            new NullComparison(new Path('user', 'oa')),
                            new NullComparison(new Path('organization', 'oa'))
                        ]
                    )
                ]
            ),
            $criteria->getExpression()
        );
    }

    public function testProcessWhenAclClassEqualToActivityClass()
    {
        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');
        $criteria->setOption(ActivityListAccessRule::ACTIVITY_OWNER_TABLE_ALIAS, 'oa');

        $this->activityListProvider->expects($this->once())
            ->method('getSupportedActivities')
            ->willReturn([self::TEST_ACTIVITY_CLASS]);
        $this->activityListProvider->expects($this->once())
            ->method('getSupportedOwnerActivity')
            ->with(self::TEST_ACTIVITY_CLASS)
            ->willReturn(self::TEST_ACTIVITY_CLASS);

        $this->builder->expects($this->once())
            ->method('getAclConditionData')
            ->with(self::TEST_ACTIVITY_CLASS, 'VIEW')
            ->willReturn(['owner', [5,7,6], 'organization', 1, false]);

        $this->rule->process($criteria);
        $this->assertEquals(
            new CompositeExpression(
                CompositeExpression::TYPE_OR,
                [
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        [
                            new Comparison(new Path('user', 'oa'), Comparison::IN, [5, 7, 6]),
                            new Comparison(new Path('organization', 'oa'), Comparison::EQ, 1),
                            new Comparison(
                                new Path('relatedActivityClass'),
                                Comparison::EQ,
                                self::TEST_ACTIVITY_CLASS
                            )
                        ]
                    ),
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        [
                            new NullComparison(new Path('user', 'oa')),
                            new NullComparison(new Path('organization', 'oa'))
                        ]
                    )
                ]
            ),
            $criteria->getExpression()
        );
    }

    public function testProcessWhenAclClassNotEqualToActivityClass()
    {
        $criteria = new Criteria(AccessRuleWalker::ORM_RULES_TYPE, ActivityList::class, 'e');
        $criteria->setOption(ActivityListAccessRule::ACTIVITY_OWNER_TABLE_ALIAS, 'oa');

        $activityAclClass = 'Test\AnotherEntity';

        $this->activityListProvider->expects($this->once())
            ->method('getSupportedActivities')
            ->willReturn([self::TEST_ACTIVITY_CLASS]);
        $this->activityListProvider->expects($this->once())
            ->method('getSupportedOwnerActivity')
            ->with(self::TEST_ACTIVITY_CLASS)
            ->willReturn($activityAclClass);

        $this->builder->expects($this->once())
            ->method('getAclConditionData')
            ->with($activityAclClass, 'VIEW')
            ->willReturn(['owner', [5,7,6], 'organization', 1, false]);

        $this->rule->process($criteria);
        $this->assertEquals(
            new CompositeExpression(
                CompositeExpression::TYPE_OR,
                [
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        [
                            new Comparison(new Path('user', 'oa'), Comparison::IN, [5, 7, 6]),
                            new Comparison(new Path('organization', 'oa'), Comparison::EQ, 1),
                            new Comparison(
                                new Path('relatedActivityClass'),
                                Comparison::EQ,
                                self::TEST_ACTIVITY_CLASS
                            )
                        ]
                    ),
                    new CompositeExpression(
                        CompositeExpression::TYPE_AND,
                        [
                            new NullComparison(new Path('user', 'oa')),
                            new NullComparison(new Path('organization', 'oa'))
                        ]
                    )
                ]
            ),
            $criteria->getExpression()
        );
    }
}
