<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Provider;

use Doctrine\Common\Util\ClassUtils;
use Oro\Bundle\EmailBundle\Model\EmailAttribute;
use Oro\Bundle\EmailBundle\Model\Recipient;
use Oro\Bundle\EmailBundle\Model\RecipientEntity;
use Oro\Bundle\EmailBundle\Provider\EmailAttributeProvider;
use Oro\Bundle\EmailBundle\Provider\EmailRecipientsHelper;
use Oro\Bundle\EmailBundle\Provider\RelatedEmailsProvider;
use Oro\Bundle\EmailBundle\Tests\Unit\Stub\CustomerStub;
use Oro\Bundle\EmailBundle\Tests\Unit\Stub\OrderStub;
use Oro\Bundle\EmailBundle\Tests\Unit\Stub\UserStub;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;
use Oro\Bundle\EntityBundle\Provider\EntityFieldProvider;
use Oro\Bundle\LocaleBundle\Formatter\NameFormatter;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RelatedEmailsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var AuthorizationCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $authorizationChecker;

    /** @var TokenAccessorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var NameFormatter|\PHPUnit\Framework\MockObject\MockObject */
    private $nameFormatter;

    /** @var EmailAddressHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $emailAddressHelper;

    /** @var EmailRecipientsHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $emailRecipientsHelper;

    /** @var EntityFieldProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $entityFieldProvider;

    /** @var EmailAttributeProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $emailAttributeProvider;

    /** @var RelatedEmailsProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->nameFormatter = $this->createMock(NameFormatter::class);
        $this->emailAddressHelper = $this->createMock(EmailAddressHelper::class);
        $this->emailRecipientsHelper = $this->createMock(EmailRecipientsHelper::class);
        $this->entityFieldProvider = $this->createMock(EntityFieldProvider::class);
        $this->emailAttributeProvider = $this->createMock(EmailAttributeProvider::class);

        $this->provider = new RelatedEmailsProvider(
            $this->authorizationChecker,
            $this->tokenAccessor,
            $this->emailRecipientsHelper,
            $this->entityFieldProvider,
            $this->emailAttributeProvider
        );
    }

    /**
     * @param object $object
     * @param int $depth
     * @param bool $ignoreAcl
     * @param Organization $organization
     * @param bool $isObjectAllowedForOrganization
     * @param bool $viewGranted
     * @param object $tokenUser
     * @param Recipient[] $expected
     *
     * @dataProvider getRecipientsSkippingRecipientsCalculationDataProvider
     */
    public function testGetRecipientsSkippingRecipientsCalculation(
        $object,
        $depth,
        $ignoreAcl,
        $organization,
        $isObjectAllowedForOrganization,
        $viewGranted,
        $tokenUser,
        $expected
    ) {
        $this->emailRecipientsHelper->expects(self::any())
            ->method('isObjectAllowedForOrganization')
            ->with($object, $organization)
            ->willReturn($isObjectAllowedForOrganization);

        $this->authorizationChecker->expects(self::any())
            ->method('isGranted')
            ->with('VIEW', $object)
            ->willReturn($viewGranted);

        $this->tokenAccessor->expects(self::any())
            ->method('getUser')
            ->willReturn($tokenUser);

        $this->emailAttributeProvider->expects(self::never())
            ->method('getAttributes');

        $this->entityFieldProvider->expects(self::never())
            ->method('getRelations');

        $this->emailAttributeProvider->expects(self::never())
            ->method('createEmailsFromAttributes');

        $this->emailRecipientsHelper->expects(self::never())
            ->method('createRecipientsFromEmails');

        self::assertEquals(
            $expected,
            $this->provider->getRecipients($object, $depth, $ignoreAcl, $organization)
        );
    }

    /**
     * @return array
     */
    public function getRecipientsSkippingRecipientsCalculationDataProvider()
    {
        $object = new \stdClass();

        return [
            'access denied for organization' => [
                'object' => $object,
                'depth' => 1,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'isObjectAllowedForOrganization' => false,
                'viewGranted' => true,
                'tokenUser' => $object,
                'expected' => [],
            ],
            'depth set, acl ignored, view granted, object is not token user' => [
                'object' => $object,
                'depth' => 1,
                'ignoreAcl' => true,
                'organization' => new Organization(),
                'isObjectAllowedForOrganization' => true,
                'viewGranted' => true,
                'tokenUser' => new User(),
                'expected' => [],
            ],
            'depth not set, acl not ignored, view granted, object is token user' => [
                'object' => $object,
                'depth' => null,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'isObjectAllowedForOrganization' => true,
                'viewGranted' => true,
                'tokenUser' => $object,
                'expected' => [],
            ],
            'depth set, acl not ignored, view not granted, object is not token user' => [
                'object' => $object,
                'depth' => 1,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'isObjectAllowedForOrganization' => true,
                'viewGranted' => false,
                'tokenUser' => new User(),
                'expected' => [],
            ],
        ];
    }

    /**
     * @param object $object
     * @param int $depth
     * @param bool $ignoreAcl
     * @param Organization $organization
     * @param array $relations
     * @param array $attributes
     * @param array $attributesFromRelations
     * @param array $emailsFromAttributes
     * @param array $recipientsFromEmails
     * @param Recipient[] $expected
     *
     * @dataProvider getRecipientsDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function testGetRecipients(
        $object,
        $depth,
        $ignoreAcl,
        $organization,
        $relations,
        $attributes,
        $attributesFromRelations,
        $emailsFromAttributes,
        $recipientsFromEmails,
        $expected
    ) {
        $this->emailRecipientsHelper->expects(self::once())
            ->method('isObjectAllowedForOrganization')
            ->with($object, $organization)
            ->willReturn(true);

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('VIEW', $object)
            ->willReturn(true);

        $this->tokenAccessor->expects(self::never())
            ->method('getUser');

        $className = ClassUtils::getClass($object);

        $this->emailAttributeProvider->expects(self::any())
            ->method('getAttributes')
            ->with($className)
            ->willReturn($attributes);

        $this->entityFieldProvider->expects(self::any())
            ->method('getRelations')
            ->with($className)
            ->willReturn($relations);

        $this->emailAttributeProvider->expects(self::any())
            ->method('createEmailsFromAttributes')
            ->with(array_merge($attributes, $attributesFromRelations), $object)
            ->willReturn($emailsFromAttributes);

        $this->emailRecipientsHelper->expects(self::any())
            ->method('createRecipientsFromEmails')
            ->with($emailsFromAttributes, $object)
            ->willReturn($recipientsFromEmails);

        self::assertEquals(
            $expected,
            $this->provider->getRecipients($object, $depth, $ignoreAcl, $organization)
        );
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getRecipientsDataProvider()
    {
        $object = new \stdClass();

        return [
            'empty attributes, empty relations' => [
                'object' => $object,
                'depth' => 2,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'relations' => [],
                'attributes' => [],
                'attributesFromRelations' => [],
                'emailsFromAttributes' => [],
                'recipientsFromEmails' => [],
                'expected' => [],
            ],
            'attributes set, empty relations' => [
                'object' => $object,
                'depth' => 2,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'relations' => [],
                'attributes' => [
                    new EmailAttribute('email'),
                    new EmailAttribute('emailField'),
                ],
                'attributesFromRelations' => [],
                'emailsFromAttributes' => [
                    'admin@example.com' => '"John Doe" <admin@example.com>',
                ],
                'recipientsFromEmails' => [
                    '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                        'admin@example.com',
                        '"John Doe" <admin@example.com>',
                        new RecipientEntity(
                            'Oro\Bundle\UserBundle\Entity\User',
                            1,
                            'John Doe (User)',
                            'ORO'
                        )
                    )
                ],
                'expected' => [
                    '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                        'admin@example.com',
                        '"John Doe" <admin@example.com>',
                        new RecipientEntity(
                            'Oro\Bundle\UserBundle\Entity\User',
                            1,
                            'John Doe (User)',
                            'ORO'
                        )
                    )
                ],
            ],
            'attributes set, relations set, depth 1' => [
                'object' => $object,
                'depth' => 1,
                'ignoreAcl' => false,
                'organization' => new Organization(),
                'relations' => [
                    'owner' => [
                        'name' => 'owner',
                        'type' => 'ref-one',
                        'label' => 'Owner',
                        'relation_type' => 'ref-one',
                        'related_entity_name' => 'Oro\Bundle\OrganizationBundle\Entity\BusinessUnit',
                    ],
                ],
                'attributes' => [
                    new EmailAttribute('email'),
                    new EmailAttribute('emailField'),
                ],
                'attributesFromRelations' => [],
                'emailsFromAttributes' => [
                    'admin@example.com' => '"John Doe" <admin@example.com>',
                ],
                'recipientsFromEmails' => [
                    '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                        'admin@example.com',
                        '"John Doe" <admin@example.com>',
                        new RecipientEntity(
                            'Oro\Bundle\UserBundle\Entity\User',
                            1,
                            'John Doe (User)',
                            'ORO'
                        )
                    )
                ],
                'expected' => [
                    '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                        'admin@example.com',
                        '"John Doe" <admin@example.com>',
                        new RecipientEntity(
                            'Oro\Bundle\UserBundle\Entity\User',
                            1,
                            'John Doe (User)',
                            'ORO'
                        )
                    )
                ],
            ],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testGetRecipientsRecursive()
    {
        $user = new UserStub(1, 'user@example.com');
        $customer = new CustomerStub();
        $order = new OrderStub($user, $customer);

        $depth = 2;
        $ignoreAcl = false;
        $organization = new Organization();
        $orderRelations = [
            'user' => [
                'name' => 'user',
                'type' => 'ref-one',
                'label' => 'User',
                'relation_type' => 'ref-one',
                'related_entity_name' => 'Oro\Bundle\EmailBundle\Tests\Unit\Stub\UserStub',
            ],
            'customer' => [
                'name' => 'customer',
                'type' => 'ref-one',
                'label' => 'Customer',
                'relation_type' => 'ref-one',
                'related_entity_name' => 'Oro\Bundle\EmailBundle\Tests\Unit\Stub\CustomerStub',
            ],
        ];
        $customerRelations = [
            'user' => [
                'name' => 'user',
                'type' => 'ref-one',
                'label' => 'User',
                'relation_type' => 'ref-one',
                'related_entity_name' => 'Oro\Bundle\EmailBundle\Tests\Unit\Stub\UserStub',
            ],
        ];
        $orderAttributes = [
            new EmailAttribute('email'),
            new EmailAttribute('emailField'),
        ];
        $customerAttributes = [];
        $attributesFromRelations = [
            new EmailAttribute('user', true),
        ];
        $emailsFromOrderAttributes = [
            'admin@example.com' => '"John Doe" <admin@example.com>',
        ];
        $emailsFromCustomerAttributes = [
            'customer@example.com' => '"Sam Smith" <customer@example.com>',
        ];
        $recipientsFromOrderEmails = [
            '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                'admin@example.com',
                '"John Doe" <admin@example.com>',
                new RecipientEntity(
                    'Oro\Bundle\UserBundle\Entity\User',
                    1,
                    'John Doe (User)',
                    'ORO'
                )
            )
        ];
        $recipientsFromCustomerEmails = [
            '"Sam Smith" <customer@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                'customer@example.com',
                '"Sam Smith" <customer@example.com>',
                new RecipientEntity(
                    'Oro\Bundle\UserBundle\Entity\User',
                    1,
                    'Sam Smith (User)',
                    'ORO'
                )
            )
        ];
        $expected = [
            '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                'admin@example.com',
                '"John Doe" <admin@example.com>',
                new RecipientEntity(
                    'Oro\Bundle\UserBundle\Entity\User',
                    1,
                    'John Doe (User)',
                    'ORO'
                )
            ),
            '"Sam Smith" <customer@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                'customer@example.com',
                '"Sam Smith" <customer@example.com>',
                new RecipientEntity(
                    'Oro\Bundle\UserBundle\Entity\User',
                    1,
                    'Sam Smith (User)',
                    'ORO'
                )
            )
        ];

        $this->emailRecipientsHelper->expects(self::exactly(2))
            ->method('isObjectAllowedForOrganization')
            ->withConsecutive(
                [$order, $organization],
                [$customer, $organization]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $this->authorizationChecker->expects(self::exactly(2))
            ->method('isGranted')
            ->withConsecutive(
                ['VIEW', $order],
                ['VIEW', $customer]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $this->tokenAccessor->expects(self::never())
            ->method('getUser');

        $orderClassName = ClassUtils::getClass($order);
        $customerClassName = ClassUtils::getClass($customer);

        $this->emailAttributeProvider->expects(self::exactly(2))
            ->method('getAttributes')
            ->withConsecutive([$orderClassName], [$customerClassName])
            ->willReturnOnConsecutiveCalls($orderAttributes, $customerAttributes);

        $this->entityFieldProvider->expects(self::exactly(2))
            ->method('getRelations')
            ->withConsecutive([$orderClassName], [$customerClassName])
            ->willReturnOnConsecutiveCalls($orderRelations, $customerRelations);

        $this->emailAttributeProvider->expects(self::exactly(2))
            ->method('createEmailsFromAttributes')
            ->withConsecutive(
                [$customerAttributes, $customer],
                [array_merge($orderAttributes, $attributesFromRelations), $order]
            )
            ->willReturnOnConsecutiveCalls($emailsFromCustomerAttributes, $emailsFromOrderAttributes);

        $this->emailRecipientsHelper->expects(self::exactly(2))
            ->method('createRecipientsFromEmails')
            ->withConsecutive(
                [$emailsFromCustomerAttributes, $customer],
                [$emailsFromOrderAttributes, $order]
            )
            ->willReturnOnConsecutiveCalls($recipientsFromCustomerEmails, $recipientsFromOrderEmails);

        self::assertEquals(
            $expected,
            $this->provider->getRecipients($order, $depth, $ignoreAcl, $organization)
        );
    }

    public function testGetEmails()
    {
        $object = new \stdClass();
        $depth = 2;
        $ignoreAcl = false;
        $organization = null;
        $relations = [];
        $attributes = [
            new EmailAttribute('email'),
            new EmailAttribute('emailField'),
        ];
        $attributesFromRelations = [];
        $emailsFromAttributes = [
            'admin@example.com' => '"John Doe" <admin@example.com>',
        ];
        $recipientsFromEmails = [
            '"John Doe" <admin@example.com>|Oro\Bundle\UserBundle\Entity\User|ORO' => new Recipient(
                'admin@example.com',
                '"John Doe" <admin@example.com>',
                new RecipientEntity(
                    'Oro\Bundle\UserBundle\Entity\User',
                    1,
                    'John Doe (User)',
                    'ORO'
                )
            )
        ];
        $expected = [
            'admin@example.com' => '"John Doe" <admin@example.com>'
        ];

        $this->emailRecipientsHelper->expects(self::once())
            ->method('isObjectAllowedForOrganization')
            ->with($object, $organization)
            ->willReturn(true);

        $this->authorizationChecker->expects(self::once())
            ->method('isGranted')
            ->with('VIEW', $object)
            ->willReturn(true);

        $this->tokenAccessor->expects(self::never())
            ->method('getUser');

        $className = ClassUtils::getClass($object);

        $this->emailAttributeProvider->expects(self::any())
            ->method('getAttributes')
            ->with($className)
            ->willReturn($attributes);

        $this->entityFieldProvider->expects(self::any())
            ->method('getRelations')
            ->with($className)
            ->willReturn($relations);

        $this->emailAttributeProvider->expects(self::any())
            ->method('createEmailsFromAttributes')
            ->with(array_merge($attributes, $attributesFromRelations), $object)
            ->willReturn($emailsFromAttributes);

        $this->emailRecipientsHelper->expects(self::any())
            ->method('createRecipientsFromEmails')
            ->with($emailsFromAttributes, $object)
            ->willReturn($recipientsFromEmails);

        self::assertEquals(
            $expected,
            $this->provider->getEmails($object, $depth, $ignoreAcl)
        );
    }
}
