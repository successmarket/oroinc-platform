<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Functional;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

class ControllersTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient(
            [],
            array_merge(self::generateBasicAuthHeader(), array('HTTP_X-CSRF-Header' => 1))
        );
        $this->client->useHashNavigation(true);
    }

    public function testIndex()
    {
        $this->client->request('GET', $this->getUrl('oro_business_unit_index'));
        $result = $this->client->getResponse();
        self::assertHtmlResponseStatusCodeEquals($result, 200);
    }

    /**
     * @return array
     */
    protected function getUser()
    {
        $request = array(
            'user' => array(
                'username' => 'user_' . mt_rand(),
                'email' => 'test_'  . mt_rand() . '@test.com',
                'enabled' => '1',
                'plainPassword' => '1231231q',
                'firstName' => 'firstName',
                'lastName' => 'lastName',
                'roles' => array('3'),
                'owner' => '1'
            )
        );
        $this->ajaxRequest('POST', $this->getUrl('oro_api_post_user'), $request);

        $result = self::getJsonResponseContent($this->client->getResponse(), 201);
        $result['request'] = $request;

        return $result;
    }

    public function testCreate()
    {
        $user = $this->getUser();
        $crawler = $this->client->request('GET', $this->getUrl('oro_business_unit_create'));
        /** @var Form $form */
        $form = $crawler->selectButton('Save and Close')->form();
        $form['oro_business_unit_form[name]'] = 'testBU';
        $form['oro_business_unit_form[parentBusinessUnit]'] = null;
        $form['oro_business_unit_form[appendUsers]'] = $user['id'];
        $form['oro_business_unit_form[email]'] = 'test@test.com';
        $form['oro_business_unit_form[phone]'] = '123-123-123';
        $form['oro_business_unit_form[fax]'] = '321-321-321';

        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);

        $result = $this->client->getResponse();
        self::assertHtmlResponseStatusCodeEquals($result, 200);
        static::assertStringContainsString('Business Unit saved', $crawler->html());

        return $user;
    }

    /**
     * @depends testCreate
     * @return string
     */
    public function testUpdate()
    {
        $response = $this->client->requestGrid(
            'business-unit-grid',
            array('business-unit-grid[_filter][name][value]' => 'testBU')
        );

        $result = self::getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        $crawler = $this->client->request(
            'GET',
            $this->getUrl('oro_business_unit_update', array('id' => $result['id']))
        );
        /** @var Form $form */
        $form = $crawler->selectButton('Save and Close')->form();
        $form['oro_business_unit_form[name]'] = 'testBU_Updated';

        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);

        $result = $this->client->getResponse();

        self::assertHtmlResponseStatusCodeEquals($result, 200);
        static::assertStringContainsString('Business Unit saved', $crawler->html());

        $response = $this->client->requestGrid(
            'business-unit-grid',
            array('business-unit-grid[_filter][name][value]' => 'testBU_Updated')
        );

        $result = self::getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        return $result['id'];
    }

    /**
     * @depends testUpdate
     * @param string $id
     */
    public function testView($id)
    {
        $crawler = $this->client->request(
            'GET',
            $this->getUrl('oro_business_unit_view', array('id' => $id))
        );

        $result = $this->client->getResponse();
        self::assertHtmlResponseStatusCodeEquals($result, 200);
        static::assertStringContainsString(
            'testBU_Updated - Business Units - User Management - System',
            $crawler->html()
        );
    }

    /**
     * @depends testUpdate
     * @depends testCreate
     * @param string $id
     * @param array $user
     */
    public function testViewUsers($id, $user)
    {
        $response = $this->client->requestGrid(
            'bu-view-users-grid',
            array('bu-view-users-grid[business_unit_id]' => $id)
        );

        $result = self::getJsonResponseContent($response, 200);
        $result = reset($result['data']);

        self::assertEquals($user['request']['user']['username'], $result['username']);
    }
}
