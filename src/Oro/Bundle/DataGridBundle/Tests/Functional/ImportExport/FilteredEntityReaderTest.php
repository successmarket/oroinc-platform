<?php

namespace Oro\Bundle\DataGridBundle\Tests\Functional\Provider;

use Oro\Bundle\DataGridBundle\ImportExport\FilteredEntityReader;
use Oro\Bundle\DataGridBundle\Tests\Functional\DataFixtures\LoadGridViewData;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationToken;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\UserBundle\Entity\User;

class FilteredEntityReaderTest extends WebTestCase
{
    /** @var FilteredEntityReader */
    private $reader;

    protected function setUp(): void
    {
        $this->initClient([], $this->generateWsseAuthHeader());
        $this->setSecurityToken();

        $this->loadFixtures([LoadGridViewData::class]);
        $this->reader = $this->getContainer()->get('oro_datagrid.importexport.export_filtered_reader');
    }

    protected function setSecurityToken(): void
    {
        $container = $this->getContainer();

        /** @var User $user */
        $user = $container->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy([]);

        $token = new OrganizationToken($user->getOrganization(), ['ROLE_ADMINISTRATOR']);
        $token->setUser($user);

        $container->get('security.token_storage')
            ->setToken($token);
    }

    public function testGetIds(): void
    {
        $ids = $this->reader->getIds(User::class, [
            'filteredResultsGrid' => 'users-grid',
            'filteredResultsGridParams' => 'i=2&p=25&s[username]=-1&f[username][value]=admin&f[username][type]=1'
        ]);

        $this->assertCount(1, $ids);
    }

    public function testGetIdsForEmptyGrid(): void
    {
        $ids = $this->reader->getIds(User::class, [
            'filteredResultsGrid' => 'users-grid',
            'filteredResultsGridParams' => 'i=1&p=25&s[username]=-1&f[username][value]=unknown&f[username][type]=1'
        ]);

        $this->assertCount(1, $ids);
        $this->assertEquals([0], $ids);
    }

    public function testGetIdsWithoutGridOptions(): void
    {
        $ids = $this->reader->getIds(User::class, []);

        $this->assertCount(3, $ids);
    }

    public function testInternalReadMethodCalled(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reader must be configured with source');

        $this->reader->read();
    }
}
