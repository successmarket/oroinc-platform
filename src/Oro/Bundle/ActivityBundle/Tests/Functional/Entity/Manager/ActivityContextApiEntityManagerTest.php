<?php

namespace Oro\Bundle\ActivityBundle\Tests\Functional\Entity\Manager;

use Oro\Bundle\ActivityListBundle\Tests\Functional\DataFixtures\LoadActivityData;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\SecurityBundle\Authorization\AuthorizationChecker;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolationPerTest
 */
class ActivityContextApiEntityManagerTest extends WebTestCase
{
    /** @var AuthorizationChecker|\PHPUnit\Framework\MockObject\MockObject */
    private $authorizationChecker;

    protected function setUp(): void
    {
        $this->initClient();

        $entityAliasResolver = $this->createMock(EntityAliasResolver::class);
        $entityAliasResolver->expects($this->any())
            ->method('getPluralAlias')
            ->willReturn('sample-alias-plural');

        $this->getContainer()->set('oro_entity.entity_alias_resolver', $entityAliasResolver);

        $this->loadFixtures([
            LoadActivityData::class,
        ]);

        $this->authorizationChecker = $this->createMock(AuthorizationChecker::class);

        $manager = self::getContainer()->get('oro_activity.manager.activity_context.api');

        $managerReflection = new \ReflectionObject($manager);
        $property = $managerReflection->getProperty('authorizationChecker');
        $property->setAccessible(true);
        $property->setValue($manager, $this->authorizationChecker);
    }

    public function testGetActivityContext(): void
    {
        $activity = $this->getReference('test_activity_1');

        $this->authorizationChecker->expects($this->atLeastOnce())
            ->method('isGranted')
            ->with('VIEW', $this->getReference('test_activity_target_1'))
            ->willReturn(true);

        $manager = self::getContainer()->get('oro_activity.manager.activity_context.api');
        $result = $manager->getActivityContext(\get_class($activity), $activity->getId());

        $target = $this->getReference('test_activity_target_1');
        $expectedItem = [
            'title' => $target->getId(),
            'activityClassAlias' => 'sample-alias-plural',
            'entityId' => $activity->getId(),
            'targetId' => $target->getId(),
            'targetClassName' => 'Oro_Bundle_TestFrameworkBundle_Entity_TestActivityTarget',
            'icon' => null,
            'link' => null,
        ];

        self::assertEquals([$expectedItem], $result);
    }

    public function testGetActivityContextWhenNotGranted(): void
    {
        $activity = $this->getReference('test_activity_1');

        $this->authorizationChecker->expects($this->atLeastOnce())
            ->method('isGranted')
            ->with('VIEW', $this->getReference('test_activity_target_1'))
            ->willReturn(false);

        $manager = self::getContainer()->get('oro_activity.manager.activity_context.api');
        $result = $manager->getActivityContext(\get_class($activity), $activity->getId());

        self::assertEquals([], $result);
    }
}
