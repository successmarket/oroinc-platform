<?php

namespace Oro\Bundle\EmailBundle\Tests\Functional;

use Oro\Bundle\EmailBundle\Tests\Functional\DataFixtures\LoadEmailData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EmailControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient(array(), $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);
        $this->loadFixtures([LoadEmailData::class]);
    }

    public function testView()
    {
        $url = $this->getUrl('oro_email_view', ['id' => $this->getReference('email_1')->getId()]);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        $content = $result->getContent();
        static::assertStringContainsString('My Web Store Introduction', $content);
        static::assertStringContainsString('Thank you for signing up to My Web Store!', $content);
    }

    public function testItems()
    {
        $ids = implode(',', [
            $this->getReference('email_1')->getId(),
            $this->getReference('email_2')->getId(),
            $this->getReference('email_3')->getId()
        ]);
        $url = $this->getUrl('oro_email_items_view', ['ids' => $ids]);
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testItemsBlank()
    {
        $url = $this->getUrl('oro_email_items_view');
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $content = $result->getContent();
        $this->assertEquals("", $content);
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testCreateViewForm()
    {
        $url = $this->getUrl('oro_email_email_create', [
            '_widgetContainer' => 'dialog'
        ]);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        $content = $result->getContent();
        static::assertStringContainsString('From', $content);
    }

    public function testBody()
    {
        $url = $this->getUrl('oro_email_body', ['id' => $this->getReference('emailBody_1')->getId()]);
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
        $content = $result->getContent();
        static::assertStringContainsString('Thank you for signing up to My Web Store!', $content);
    }

    public function testActivity()
    {
        $this->markTestIncomplete('Skipped. Need activity fixture');

        $url = $this->getUrl('oro_email_activity_view', [
            'entityClass' => 'test',
            'entityId' => 1
        ]);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testAttachment()
    {
        $attachment = $this->getReference('emailAttachment_1');
        $url = $this->getUrl('oro_email_attachment', [
            'id' => $attachment->getId()
        ]);
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($result, 200);
        $this->assertResponseContentTypeEquals($result, $attachment->getContentType());
    }

    public function testGetResizedAttachmentImage()
    {
        $attachment = $this->getReference('emailAttachment_1');
        $url = $this->getUrl('oro_resize_email_attachment', [
            'id' => $attachment->getId(),
            'width' => 10,
            'height' => 10
        ]);
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($result, 200);
        $this->assertResponseContentTypeEquals($result, $attachment->getContentType());

        $path = substr($this->client->getRequest()->getPathInfo(), 1);
        $this->getContainer()->get('oro_attachment.file_manager')->deleteFile($path);
    }

    public function testDownloadAttachments()
    {
        $emailBody = $this->getReference('emailBody_1');
        $url = $this->getUrl('oro_email_body_attachments', [
            'id' => $emailBody->getId(),
        ]);
        $this->client->request('GET', $url, [], [], $this->generateNoHashNavigationHeader());
        $result = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($result, 200);
        $this->assertResponseContentTypeEquals($result, 'application/zip');
    }

    public function testEmails()
    {
        $url = $this->getUrl('oro_email_widget_emails', ['_widgetContainer' => 'dialog']);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testBaseEmails()
    {
        $url = $this->getUrl('oro_email_widget_base_emails', ['_widgetContainer' => 'dialog']);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testUserEmails()
    {
        $url = $this->getUrl('oro_email_user_emails', ['_widgetContainer' => 'dialog']);
        $this->client->request('GET', $url);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);
    }

    public function testEmailToggleSeen()
    {
        $url = $this->getUrl('oro_email_toggle_seen', ['id' => $this->getReference('emailUser_1')->getId()]);
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful']);
    }

    public function testEmailMarkSeen()
    {
        $emailId = $this->getReference('emailUser_1')->getEmail()->getId();

        $url = $this->getUrl('oro_email_mark_seen', ['id' => $emailId, 'status' => 1]);
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful']);

        $url = $this->getUrl('oro_email_mark_seen', ['id' => $emailId, 'status' => 0, 'checkThread' => 0]);
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful']);
    }

    public function testMarkAllEmailsAsSeen()
    {
        $url = $this->getUrl('oro_email_mark_all_as_seen');
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful']);
    }

    public function testMarkReadMass()
    {
        $url = $this->getUrl(
            'oro_email_mark_massaction',
            [
                'gridName' => 'user-email-grid',
                'actionName' => 'emailmarkread',
                'user-email-grid[userId]' => $this->getReference('simple_user')->getId(),
                'inset' => 1,
                'values' => $this->getReference('emailUser_for_mass_mark_test')->getId()
            ]
        );
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful'] === true);
        $this->assertTrue($data['count'] === 1);
    }

    public function testMarkUnreadMass()
    {
        $url = $this->getUrl(
            'oro_email_mark_massaction',
            [
                'gridName' => 'user-email-grid',
                'actionName' => 'emailmarkunread',
                'user-email-grid[userId]' => $this->getReference('simple_user')->getId(),
                'inset' => 1,
                'values' => $this->getReference('emailUser_for_mass_mark_test')->getId()
            ]
        );
        $this->ajaxRequest('POST', $url);
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $data = json_decode($result->getContent(), true);
        $this->assertTrue($data['successful'] === true);
        $this->assertTrue($data['count'] === 1);
    }

    public function testReply()
    {
        $email = $this->getReference('email_1');
        $id = $email->getId();
        $url = $this->getUrl('oro_email_email_reply', ['id' => $id, '_widgetContainer' => 'dialog']);
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($response, 200);
        $crawler = $this->client->getCrawler();
        $this->assertEquals(1, $crawler->filter('div.widget-content input[name=\'oro_email_email[cc]\']')->count());
        $this->assertEquals(
            1,
            $crawler->filter('div.widget-content input[value=\''
                . base64_encode($email->getFromName())
                . '\']')->count()
        );
        $cc = $email->getCc()->first()->getEmailAddress()->getEmail();
        $this->assertEquals(
            0,
            $crawler->filter('div.widget-content input[value=\'' . $cc . '\']')->count()
        );
        $bcc = $email->getBcc()->first()->getEmailAddress()->getEmail();
        $this->assertEquals(
            0,
            $crawler->filter('div.widget-content input[value=\'' . $bcc . '\']')->count()
        );
    }

    public function testReplyAll()
    {
        $email = $this->getReference('email_1');
        $id = $email->getId();
        $url = $this->getUrl('oro_email_email_reply_all', ['id' => $id, '_widgetContainer' => 'dialog']);
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($response, 200);
        $crawler = $this->client->getCrawler();
        $this->assertEquals(1, $crawler->filter('div.widget-content input[name=\'oro_email_email[cc]\']')->count());
        $this->assertEquals(
            1,
            $crawler->filter('div.widget-content input[value=\''
                . base64_encode($email->getFromName())
                . '\']')->count()
        );
        $cc = $email->getCc()->first()->getEmailAddress()->getEmail();
        $this->assertEquals(
            1,
            $crawler->filter('div.widget-content input[value=\'' . base64_encode($cc) . '\']')->count()
        );
        $bcc = $email->getBcc()->first()->getEmailAddress()->getEmail();
        $this->assertEquals(
            0,
            $crawler->filter('div.widget-content input[value=\'' . $bcc . '\']')->count()
        );
    }

    public function testGetLastEmail()
    {
        $url = $this->getUrl('oro_email_last');
        $this->client->request('GET', $url);

        $response = $this->getJsonResponseContent($this->client->getResponse(), 200);
        $this->assertEquals(1, $response['count']);
        $this->assertCount(1, $response['emails']);
    }

    public function testAccessRoutesInWrongWayValidation()
    {
        $this->client->followRedirects();
        $emailId = $this->getReference('email_1')->getId();

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_view_group', ['id' => $emailId])
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_email_create')
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_email_reply', ['id' => $emailId])
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_email_reply_all', ['id' => $emailId])
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_email_forward', ['id' => $emailId])
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_widget_base_emails')
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);

        $this->client->request(
            'GET',
            $this->getUrl('oro_email_widget_emails')
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 404);
    }

    /**
     * @dataProvider autocompleteRecipientActionProvider
     *
     * @param string $method
     * @param bool $searchById
     */
    public function testAutocompleteRecipientActionById(string $method, bool $searchById): void
    {
        /** @var User $user */
        $user = $this->getReference('simple_user2');
        $userString = sprintf('"%s" <%s>', $user->getFullName(), $user->getEmail());

        $this->client->request(
            $method,
            $this->getUrl(
                'oro_email_autocomplete_recipient',
                [
                    'entityClass' => User::class,
                    'entityId' => $user->getId(),
                    'query' => $searchById ? base64_encode($userString) : $user->getUsername(),
                    'search_by_id' => $searchById,
                    'per_page' => 100
                ]
            )
        );

        $response = $this->client->getResponse();
        $this->assertResponseStatusCodeEquals($response, 200);

        $data = json_decode($response->getContent(), true);
        $context = ' (User)';
        $expected = [
            [
                'text' => 'Contexts',
                'children' => [
                    [
                        'id' => base64_encode($userString),
                        'text' => $userString . $context,
                        'data' => \json_encode(
                            [
                                'key' => $userString,
                                'contextText' => $user->getFullName() . $context,
                                'contextValue' => [
                                    'entityClass' => User::class,
                                    'entityId' => $user->getId(),
                                ],
                                'organization' => $user->getOrganization()->getName(),
                            ]
                        )
                    ]
                ]
            ]
        ];

        $this->assertArrayHasKey('results', $data);
        $this->assertEquals($searchById ? $expected[0]['children'] : $expected, $data['results']);
    }

    /**
     * @return array
     */
    public function autocompleteRecipientActionProvider(): array
    {
        return [
            [
                'method' => 'GET',
                'searchById' => false
            ],
            [
                'method' => 'POST',
                'searchById' => false
            ],
            [
                'method' => 'GET',
                'searchById' => true
            ],
            [
                'method' => 'POST',
                'searchById' => true
            ],
        ];
    }
}
