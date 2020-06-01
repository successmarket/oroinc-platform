<?php

namespace Oro\Bundle\EmailBundle\Tests\Behat\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Oro\Bundle\EmailBundle\Mailer\DirectMailer;
use Oro\Bundle\EmailBundle\Manager\TemplateEmailManager;
use Oro\Bundle\EmailBundle\Model\EmailTemplateCriteria;
use Oro\Bundle\EmailBundle\Model\From;
use Oro\Bundle\EmailBundle\Tests\Behat\Mock\Mailer\DirectMailerDecorator;
use Oro\Bundle\TestFrameworkBundle\Behat\Client\FileDownloader;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\AssertTrait;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\OroFeatureContext;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class EmailContext extends OroFeatureContext implements KernelAwareContext
{
    use AssertTrait, KernelDictionary;

    /** @var DirectMailerDecorator */
    private $mailer;

    /** @var string */
    private $downloadedFile;

    /**
     * @BeforeScenario
     * @AfterScenario
     */
    public function clear()
    {
        $mailer = $this->getMailer();
        if ($mailer instanceof DirectMailerDecorator) {
            $mailer->clear();
        }
    }

    /**
     * @Given /^Email should contains the following "([^"]*)" text$/
     * @Given /^An email containing the following "([^"]*)" text was sent$/
     * @Given /^Email should contains the following text:/
     *
     * @param string $text
     */
    public function emailShouldContainsTheFollowingText($text)
    {
        self::assertNotEmpty($text, 'Assertion text can\'t be empty.');

        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $pattern = $this->getPattern($text);
        $found = false;

        /** @var \Swift_Mime_Message $message */
        foreach ($mailer->getSentMessages() as $message) {
            $data = array_map(
                function ($field) use ($message) {
                    return $this->getMessageData($message, $field);
                },
                ['From', 'To', 'Cc', 'Bcc', 'Subject', 'Body']
            );

            $found = (bool) preg_match($pattern, implode(' ', $data));
            if ($found !== false) {
                break;
            }
        }

        self::assertNotFalse(
            $found,
            sprintf(
                'Sent emails bodies don\'t contain expected text. The following messages has been sent: %s',
                print_r($this->getSentMessagesData($mailer->getSentMessages()), true)
            )
        );
    }

    /**
     * Example: Then Email should contains the following:
     *            | From    | admin@example.com |
     *            | To      | user1@example.com |
     *            | Cc      | user2@example.com |
     *            | Bcc     | user3@example.com |
     *            | Subject | Test Subject      |
     *            | Body    | Test Body         |
     *
     * @Given /^Email should contains the following:/
     * @Given /^An email containing the following was sent:/
     *
     * @param TableNode $table
     */
    public function emailShouldContainsTheFollowing(TableNode $table)
    {
        self::assertNotEmpty($table, 'Assertions list must contain at least one row.');

        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $expectedRows = [];
        foreach ($table->getRows() as list($field, $text)) {
            //Keys makes possible to use multiple Body field in expected table
            $expectedRows[] = ['field' => $field, 'pattern' => $this->getPattern($text)];
        }

        $sentMessages = $mailer->getSentMessages();

        self::assertNotEmpty($sentMessages, 'There are no sent messages');

        $found = false;
        /** @var \Swift_Mime_Message $message */
        foreach ($sentMessages as $message) {
            foreach ($expectedRows as $expectedContent) {
                $found = (bool) preg_match(
                    $expectedContent['pattern'],
                    $this->getMessageData($message, $expectedContent['field'])
                );
                if ($found === false) {
                    break;
                }
            }

            if ($found) {
                break;
            }
        }

        self::assertNotFalse(
            $found,
            sprintf(
                'Sent emails bodies don\'t contain expected data. The following messages has been sent: %s',
                print_r($this->getSentMessagesData($mailer->getSentMessages()), true)
            )
        );
    }

    /**
     * @param array $messages
     *
     * @return array
     */
    private function getSentMessagesData(array $messages): array
    {
        $messagesData = [];
        foreach ($messages as $message) {
            $item = [];
            foreach (['From', 'To', 'Cc', 'Bcc', 'Subject', 'Body'] as $field) {
                $item[$field] = $this->getMessageData($message, $field);
            }
            $messagesData[] = $item;
        }

        return $messagesData;
    }

    /**
     * Example: Then Email should not contains the following:
     *            | From    | admin@example.com |
     *            | To      | user1@example.com |
     *            | Cc      | user2@example.com |
     *            | Bcc     | user3@example.com |
     *            | Subject | Test Subject      |
     *            | Body    | Test Body         |
     *
     * @Given /^Email should not contains the following:/
     * @Given /^An email does not containing the following was sent:/
     *
     * @param TableNode $table
     */
    public function emailShouldNotContainsTheFollowing(TableNode $table)
    {
        self::assertNotEmpty($table, 'Assertions list must contain at least one row.');

        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $expectedRows = [];
        foreach ($table->getRows() as list($field, $text)) {
            //Keys makes possible to use multiple Body field in expected table
            $expectedRows[] = ['field' => $field, 'pattern' => $this->getPattern($text)];
        }

        $sentMessages = $mailer->getSentMessages();

        self::assertNotEmpty($sentMessages, 'There are no sent messages');

        $found = false;
        /** @var \Swift_Mime_Message $message */
        foreach ($sentMessages as $message) {
            foreach ($expectedRows as $expectedContent) {
                $found = (bool) preg_match(
                    $expectedContent['pattern'],
                    $this->getMessageData($message, $expectedContent['field'])
                );
                if ($found === false) {
                    break;
                }
            }

            if ($found) {
                break;
            }
        }

        self::assertFalse(
            $found,
            sprintf(
                'Sent emails contains extra data. The following messages has been sent: %s',
                print_r($this->getSentMessagesData($mailer->getSentMessages()), true)
            )
        );
    }

    /**
     * @Given /^take the link from email and download the file from this link$/
     */
    public function downloadFileFromEmail()
    {
        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/mi';
        $found = null;

        /** @var \Swift_Mime_Message $message */
        foreach ($mailer->getSentMessages() as $message) {
            $body = $message->getBody();

            if (!preg_match($pattern, $body, $matches)) {
                continue;
            }

            $found = $matches[2];
            break;
        }

        if ($found) {
            $this->downloadedFile = tempnam(
                sprintf(
                    '%s%svar%simport_export',
                    $this->getKernel()->getProjectDir(),
                    DIRECTORY_SEPARATOR,
                    DIRECTORY_SEPARATOR
                ),
                'file_from_email_'
            );

            self::assertTrue((new FileDownloader())->download($found, $this->downloadedFile, $this->getSession()));

            return;
        }

        self::assertNotFalse($found, 'Sent emails don\'t contain expected data.');
    }

    /**
     * @Given /^the downloaded file from email contains at least the following data:$/
     *
     * @param TableNode $expectedEntities
     */
    public function downloadedFileFromEmailMustContains(TableNode $expectedEntities)
    {
        try {
            $exportedFile = new \SplFileObject($this->downloadedFile, 'rb');
            // Treat file as CSV, skip empty lines.
            $exportedFile->setFlags(\SplFileObject::READ_CSV
                | \SplFileObject::READ_AHEAD
                | \SplFileObject::SKIP_EMPTY
                | \SplFileObject::DROP_NEW_LINE);

            $headers = $exportedFile->current();
            $expectedHeaders = $expectedEntities->getRow(0);

            foreach ($exportedFile as $line => $data) {
                $entityDataFromCsv = array_combine($headers, array_values($data));
                $expectedEntityData = array_combine($expectedHeaders, array_values($expectedEntities->getRow($line)));

                // Ensure that at least expected data is present.
                foreach ($expectedEntityData as $property => $value) {
                    static::assertEquals($value, $entityDataFromCsv[$property]);
                }
            }

            static::assertCount($exportedFile->key(), $expectedEntities->getRows());
        } finally {
            // We have to release SplFileObject before trying to delete the underlying file.
            $exportedFile = null;
            unlink($this->downloadedFile);
        }
    }

    /**
     * Example: Then email with Subject "Your RFQ has been received." containing the following was sent:
     *            | From    | admin@example.com |
     *            | To      | user1@example.com |
     *            | Cc      | user2@example.com |
     *            | Bcc     | user3@example.com |
     *            | Body    | Test Body         |
     *
     * @Given /^email with (?P<searchField>[\w]+) "(?P<searchText>(?:[^"]|\\")*)" containing the following was sent:/
     *
     * @param string $searchField
     * @param string $searchText
     * @param TableNode $table
     */
    public function emailWithFieldMustContainsTheFollowing(string $searchField, string $searchText, TableNode $table)
    {
        self::assertNotEmpty($table, 'Assertions list must contain at least one row.');

        self::assertEmailFieldValid($searchField);

        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $expectedContent = [];
        foreach ($table->getRows() as list($field, $text)) {
            $expectedContent[$field] = $this->getPattern($text);
        }

        $found = false;

        /** @var \Swift_Mime_Message $message */
        foreach ($mailer->getSentMessages() as $message) {
            if ($searchText !== $this->getMessageData($message, $searchField)) {
                continue;
            }

            foreach ($expectedContent as $field => $pattern) {
                $found = (bool) preg_match($pattern, $this->getMessageData($message, $field));
                if ($found === false) {
                    break 2;
                }
            }
        }

        self::assertNotFalse($found, 'Sent emails don\'t contain expected data.');
    }

    /**
     * Example: Then email with Subject "Your RFQ has been received." was not sent:
     *
     * @Given /^email with (?P<searchField>[\w]+) "(?P<searchText>(?:[^"]|\\")*)" was not sent/
     *
     * @param string $searchField
     * @param string $searchText
     */
    public function emailWithFieldIsNotSent(string $searchField, string $searchText)
    {
        self::assertEmailFieldValid($searchField);

        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        /** @var \Swift_Mime_Message $message */
        foreach ($mailer->getSentMessages() as $message) {
            if ($searchText === $this->getMessageData($message, $searchField)) {
                self::fail(sprintf('Email with %s \"%s\" was not expected to be sent', $searchField, $searchText));
            }
        }
    }

    /**
     * Example: Then email date less than "+3 days"
     *
     * @Given /^email date (?P<condition>[\w]+) than "(?P<date>[-+\s\w]+)"$/
     *
     * @param string $condition
     * @param string $expectedDate
     */
    public function assertDateInEmail(string $condition, string $expectedDate)
    {
        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $found = null;
        /** @var \Swift_Mime_Message $message */
        foreach ($mailer->getSentMessages() as $message) {
            $found = (bool) preg_match(
                '/\D{2,3}\s\d{1,2},\s\d{4} at \d{1,2}:\d{2}\s(AM|PM)/',
                $message->getBody(),
                $matches
            );
            if ($found) {
                $date = \DateTime::createFromFormat('M d, Y ?? h:i A', $matches[0], new \DateTimeZone('UTC'));
                $result = null;
                switch ($condition) {
                    case 'less':
                        $result = $date < new \DateTime($expectedDate, new \DateTimeZone('UTC'));
                        break;
                    case 'greater':
                        $result = $date > new \DateTime($expectedDate, new \DateTimeZone('UTC'));
                        break;
                }
                self::assertTrue($result, sprintf('Email date is not %s than %s', $condition, $expectedDate));

                break;
            }
        }

        self::assertTrue($found, 'Sent emails bodies don\'t contain dates.');
    }

    /**
     * @param string $text
     * @return string
     */
    private function getPattern($text)
    {
        return sprintf('/%s/', preg_replace('/\s+/', '[[:space:][:cntrl:]]+', preg_quote($text, '/')));
    }

    /**
     * @param \Swift_Mime_Message $message
     * @param string $field
     * @return string
     */
    private function getMessageData(\Swift_Mime_Message $message, $field)
    {
        switch (strtolower(trim($field))) {
            case 'from':
                $data = array_keys($message->getFrom());
                break;
            case 'to':
                $data = array_keys($message->getTo());
                break;
            case 'cc':
                $data = is_array($message->getCc()) ? array_keys($message->getCc()) : $message->getCc();
                break;
            case 'bcc':
                $data = is_array($message->getBcc()) ? array_keys($message->getBcc()) : $message->getBcc();
                break;
            case 'subject':
                $data = $message->getSubject();
                break;
            case 'body':
                $data = strip_tags($message->getBody());
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported email field "%s".', $field));
                break;
        }

        $messageData = implode(' ', (array) $data);

        return trim(html_entity_decode(strip_tags($messageData), ENT_QUOTES));
    }

    /**
     * @return DirectMailer
     */
    private function getMailer()
    {
        if (!$this->mailer) {
            $this->mailer = $this->getContainer()->get('oro_email.direct_mailer');
        }

        return $this->mailer;
    }

    /**
     * @param string $fieldName
     */
    private static function assertEmailFieldValid(string $fieldName): void
    {
        $allowedFields = ['From', 'To', 'Cc', 'Bcc', 'Subject', 'Body'];
        self::assertContains(
            $fieldName,
            $allowedFields,
            'Email field must be one of '.implode(', ', $allowedFields)
        );
    }

    /**
     * Example: Then I follow "Confirm" link from the email
     *
     * @Given /^(?:|I )follow "(?P<linkCaption>[^"]+)" link from the email$/
     * @Given /^(?:|I )follow link from the email$/
     *
     * @param string $linkCaption
     */
    public function followLinkFromEmail(string $linkCaption = '[^\<]+')
    {
        $mailer = $this->getMailer();
        if (!$mailer instanceof DirectMailerDecorator) {
            return;
        }

        $pattern = sprintf('/<a.*href\s*=\s*"(?P<url>[^"]+)".*>\s*%s\s*<\/a>/s', $linkCaption);

        $url = $this->spin(function () use ($mailer, $pattern) {
            $matches = [];

            /** @var \Swift_Mime_Message $message */
            foreach ($mailer->getSentMessages() as $message) {
                $text = utf8_decode(html_entity_decode($message->getBody()));
                // replace non-breaking spaces with plain spaces to be able to search
                $text = str_replace(chr(160), chr(32), $text);

                if (preg_match($pattern, $text, $matches) && isset($matches['url'])) {
                    return htmlspecialchars_decode($matches['url']);
                }
            }

            return false;
        });

        self::assertNotNull($url, sprintf('"%s" link not found in the email', $linkCaption));

        $this->visitPath($url);
    }

    /**
     * @Given /^(?:|I )send email template "(?P<templateName>(?:[^"]|\\")*)" to "(?P<username>(?:[^"]|\\")*)"$/
     *
     * @param string $templateName
     */
    public function sendEmailTemplateToUser(string $templateName, string $username): void
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $recipient = $doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

        /** @var TemplateEmailManager $emailTemplateManager */
        $emailTemplateManager = $this->getContainer()->get('oro_email.manager.template_email');

        $failedRecipients = [];
        $emailTemplateManager->sendTemplateEmail(
            From::emailAddress('no-reply@example.com'),
            [$recipient],
            new EmailTemplateCriteria($templateName),
            [],
            $failedRecipients
        );

        // Doctrine is caching email templates and after change template data not perform that changes in behat thread
        $doctrine->resetManager();
    }
}
