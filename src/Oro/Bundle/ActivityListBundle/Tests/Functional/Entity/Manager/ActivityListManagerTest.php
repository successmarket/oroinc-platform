<?php

namespace Oro\Bundle\ActivityListBundle\Tests\Functional\Entity\Manager;

use Oro\Bundle\ActivityListBundle\Entity\Manager\ActivityListManager;
use Oro\Bundle\ActivityListBundle\Tests\Functional\DataFixtures\LoadEmailActivityData;
use Oro\Bundle\SecurityBundle\Authentication\Token\UsernamePasswordOrganizationToken;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadOrganization;
use Oro\Bundle\UserBundle\Entity\User;

class ActivityListManagerTest extends WebTestCase
{
    /**
     * @var ActivityListManager
     */
    private $manager;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient([]);
        $this->loadFixtures([LoadOrganization::class, LoadEmailActivityData::class]);
        $this->manager = self::getContainer()->get(ActivityListManager::class);
    }

    /**
     * @dataProvider emailActivityProvider
     *
     * @param int $expectedCommentCount
     * @param string $activityReference
     */
    public function testGetEntityViewModelEmailWithNoThread($expectedCommentCount, $activityReference)
    {
        $organization = $this->getReference('organization');
        $user = self::getContainer()->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['username' => 'admin']);
        $adminToken = new UsernamePasswordOrganizationToken(
            $user,
            'admin',
            'key',
            $organization,
            $user->getRoles()
        );

        self::getContainer()->get('security.token_storage')->setToken($adminToken);

        $result = $this->manager->getEntityViewModel($this->getReference($activityReference));

        self::assertTrue($result['commentable']);
        self::assertEquals($expectedCommentCount, $result['commentCount']);
    }

    /**
     * @return array
     */
    public function emailActivityProvider()
    {
        return [
            'email_without_thread' => [
                'commentCount' => 1,
                'activityReference' => 'test_activity_list_1'
            ],
            'email_with_thread' => [
                'commentCount' => 3,
                'activityReference' => 'test_activity_list_2'
            ]
        ];
    }
}
