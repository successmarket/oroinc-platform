<?php

namespace Oro\Bundle\UserBundle\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Oro\Bundle\AttachmentBundle\Tests\Behat\Context\AttachmentContext;
use Oro\Bundle\AttachmentBundle\Tests\Behat\Context\AttachmentImageContext;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class UserAttachmentContext extends AttachmentContext implements KernelAwareContext
{
    private const USER_FIELD_AVATAR = 'avatar';

    /** @var AttachmentImageContext */
    private $attachmentImageContext;

    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();

        $this->attachmentImageContext = $environment->getContext(AttachmentImageContext::class);
    }

    /**
     * @Then /^(?:|I )should see avatar for user "(?P<username>[\w\s]+)"$/
     *
     * @param string $username
     */
    public function userAvatarIsGranted(string $username): void
    {
        $user = $this->getUser($username);
        $attachmentUrl = $this->attachmentImageContext->getAttachmentUrl($user, self::USER_FIELD_AVATAR);
        $resizeAttachmentUrl = $this->attachmentImageContext->getResizeAttachmentUrl($user, self::USER_FIELD_AVATAR);
        $filteredAttachmentUrl = $this->attachmentImageContext->getFilteredAttachmentUrl(
            $user,
            self::USER_FIELD_AVATAR
        );

        $this->assertResponseSuccess($this->attachmentImageContext->downloadAttachment($attachmentUrl));
        $this->assertResponseSuccess($this->downloadAttachment($resizeAttachmentUrl));
        $this->assertResponseSuccess($this->downloadAttachment($filteredAttachmentUrl));
    }

    /**
     * @Then /^(?:|I )should not see avatar for user "(?P<userNameOrEmail>[\w\s]+)"$/
     *
     * @param string $username
     */
    public function userAvatarIsNotGranted(string $username): void
    {
        $user = $this->getUser($username);
        $attachmentUrl = $this->getAttachmentUrl($user, self::USER_FIELD_AVATAR);
        $resizeAttachmentUrl = $this->attachmentImageContext->getResizeAttachmentUrl($user, self::USER_FIELD_AVATAR);
        $filteredAttachmentUrl = $this->attachmentImageContext->getFilteredAttachmentUrl(
            $user,
            self::USER_FIELD_AVATAR
        );

        $this->assertResponseFail($this->downloadAttachment($attachmentUrl));
        $this->assertResponseFail($this->downloadAttachment($resizeAttachmentUrl));
        $this->assertResponseFail($this->downloadAttachment($filteredAttachmentUrl));
    }

    /**
     * @param string $username
     *
     * @return User
     */
    private function getUser(string $username): User
    {
        /** @var UserManager $userManager */
        $userManager = $this->getContainer()->get('oro_user.manager');
        /** @var User $user */
        $user = $userManager->findUserByUsername($username);

        self::assertNotNull($user, sprintf('Could not find user with username "%s".', $username));
        $userManager->reloadUser($user);

        return $user;
    }

    /**
     * @param ResponseInterface $response
     */
    protected function assertResponseSuccess(ResponseInterface $response): void
    {
        $attachmentManager = $this->getContainer()->get('oro_attachment.manager');

        self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($attachmentManager->isImageType($response->getHeader('Content-Type')[0]));
    }

    /**
     * @param ResponseInterface $response
     */
    protected function assertResponseFail(ResponseInterface $response): void
    {
        self::assertContains($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_FORBIDDEN]);
        self::assertContains('text/html', $response->getHeader('Content-Type')[0]);
    }
}
