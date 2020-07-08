<?php

namespace Oro\Bundle\UserBundle\Tests\Functional;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

class ControllersRoleTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);
    }

    public function testIndex()
    {
        $this->client->request('GET', $this->getUrl('oro_user_role_index'));
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testCreate()
    {
        /** @var Crawler $crawler */
        $crawler = $this->client->request('GET', $this->getUrl('oro_user_role_create'));
        /** @var Form $form */
        $form = $crawler->selectButton('Save and Close')->form();

        $form['oro_user_role_form[label]'] = 'testRole';

        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);

        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        static::assertStringContainsString("Role saved", $crawler->html());
    }

    /**
     * @depends testCreate
     */
    public function testUpdate()
    {
        $response = $this->client->requestGrid(
            'roles-grid',
            ['roles-grid[_filter][label][value]' => 'testRole']
        );

        $result = $this->getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        /** @var Crawler $crawler */
        $crawler = $this->client->request(
            'GET',
            $this->getUrl('oro_user_role_update', ['id' => $result['id']])
        );

        /** @var Form $form */
        $form = $crawler->selectButton('Save and Close')->form();

        $form['oro_user_role_form[label]']       = 'testRoleUpdated';
        $form['oro_user_role_form[appendUsers]'] = 1;

        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);

        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        static::assertStringContainsString("Role saved", $crawler->html());
    }

    /**
     * @depends testUpdate
     */
    public function testGridData()
    {
        $response = $this->client->requestGrid(
            'roles-grid',
            ['roles-grid[_filter][label][value]' => 'testRoleUpdated']
        );

        $result = $this->getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        $response = $this->client->requestGrid(
            'role-users-grid',
            [
                'role-users-grid[_filter][has_role][value]' => 1,
                'role-users-grid[role_id]'                  => $result['id']
            ]
        );

        $result = $this->getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        $this->assertEquals(1, $result['id']);
    }

    /**
     * @depends testUpdate
     */
    public function testView()
    {
        $response = $this->client->requestGrid(
            'roles-grid',
            ['roles-grid[_filter][label][value]' => 'testRoleUpdated']
        );

        $result = $this->getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        /** @var Crawler $crawler */
        $crawler = $this->client->request(
            'GET',
            $this->getUrl('oro_user_role_view', ['id' => $result['id']])
        );

        static::assertStringContainsString(
            'testRoleUpdated',
            $crawler->filter('.responsive-section')->first()->html()
        );
        static::assertStringContainsString(
            'Clone',
            $crawler->filter('.navigation .title-buttons-container a')->eq(0)->html()
        );
        static::assertStringContainsString(
            'Edit',
            $crawler->filter('.navigation .title-buttons-container a')->eq(1)->html()
        );
        static::assertStringContainsString(
            'Delete',
            $crawler->filter('.navigation .title-buttons-container a')->eq(2)->html()
        );
    }
}
