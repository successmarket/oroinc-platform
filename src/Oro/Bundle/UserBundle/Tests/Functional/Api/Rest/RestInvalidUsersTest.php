<?php

namespace Oro\Bundle\UserBundle\Tests\Functional\Api\Rest;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class RestInvalidUsersTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient();
    }

    /**
     * @dataProvider usernameKeyDataProvider
     *
     * @param string $username
     * @param string $key
     */
    public function testInvalidCredentials(string $username, string $key): void
    {
        $request = [
            'user' => [
                'username' => 'user_' . mt_rand(),
                'email' => 'test_' . mt_rand() . '@test.com',
                'enabled' => 'true',
                'plainPassword' => '1231231q',
                'firstName' => 'firstName',
                'lastName' => 'lastName',
                'roles' => ['1'],
            ],
        ];
        $this->client->request(
            'POST',
            $this->getUrl('oro_api_post_user'),
            $request,
            [],
            $this->generateWsseAuthHeader($username, $key)
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 401);
    }

    /**
     * @return array
     */
    public function usernameKeyDataProvider(): array
    {
        return [
            'invalid key' => [WebTestCase::USER_NAME, 'invalid_key'],
            'invalid user' => ['invalid_user', WebTestCase::USER_PASSWORD],
        ];
    }
}
