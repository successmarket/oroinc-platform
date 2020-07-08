<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Validator;

use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Oro\Bundle\UserBundle\Validator\Constraints\UserAuthenticationFieldsConstraint;
use Oro\Bundle\UserBundle\Validator\UserAuthenticationFieldsValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class UserAuthenticationFieldsValidatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|UserManager
     */
    protected $userManager;

    /**
     * @var UserAuthenticationFieldsConstraint
     */
    protected $constraint;

    /**
     * @var ExecutionContextInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $context;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ConstraintViolationBuilderInterface
     */
    protected $violation;

    /**
     * @var UserAuthenticationFieldsValidator
     */
    protected $validator;

    protected function setUp(): void
    {
        $this->userManager = $this->getMockBuilder('Oro\Bundle\UserBundle\Entity\UserManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->violation =
            $this->getMockBuilder('Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface')
            ->getMock();

        $this->constraint = new UserAuthenticationFieldsConstraint();
        $this->context = $this->getMockBuilder('Symfony\Component\Validator\Context\ExecutionContextInterface')
            ->getMock();

        $this->validator = new UserAuthenticationFieldsValidator($this->userManager);
        $this->validator->initialize($this->context);
    }

    protected function tearDown(): void
    {
        unset($this->constraint, $this->context);
    }

    public function testConfiguration()
    {
        $this->assertEquals('oro_user.validator.user_authentication_fields', $this->constraint->validatedBy());
        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $this->constraint->getTargets());
    }

    public function testGetDefaultOption()
    {
        $this->assertNull($this->constraint->getDefaultOption());
    }

    /**
     * User username = User email, Username not in email format
     */
    public function testUsernameValid()
    {
        $user = $this->getUser(1);
        $user->setUsername('username');
        $user->setEmail('username@example.com');

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($user, $this->constraint);
    }

    /**
     * User username = User email, Username in email format
     */
    public function testUsernameValidUsernameAsEmail()
    {
        $user = $this->getUser(1);
        $user->setUsername('username@example.com');
        $user->setEmail('username@example.com');

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($user, $this->constraint);
    }

    /**
     * User with email as current user Username not exist, Username in email format
     */
    public function testUsernameValidUsernameInEmailFormat()
    {
        $user = $this->getUser(1);
        $user->setUsername('username@example.com');
        $user->setEmail('test@example.com');

        $existingUser = null;

        $this->userManager->expects($this->once())
            ->method('findUserByEmail')
            ->with('username@example.com')
            ->will($this->returnValue($existingUser));

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($user, $this->constraint);
    }

    /**
     * User username = existing user email, Username in email format
     */
    public function testUsernameNotValidUsernameInEmailFormat()
    {
        $user = $this->getUser(1);
        $user->setUsername('username@example.com');
        $user->setEmail('test@example.com');

        $existingUser = $this->getUser(2);
        $existingUser->setUsername('username');
        $existingUser->setEmail('username@example.com');

        $this->userManager->expects($this->once())
            ->method('findUserByEmail')
            ->with('username@example.com')
            ->will($this->returnValue($existingUser));

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->willReturn($this->violation);

        $this->violation->expects($this->once())
            ->method('atPath')
            ->with(UserAuthenticationFieldsValidator::VIOLATION_PATH)
            ->willReturnSelf();

        $this->violation->expects($this->once())
            ->method('addViolation');

        $this->validator->validate($user, $this->constraint);
    }

    public function testUsernameIsNull()
    {
        $user = $this->getUser(1);
        $user->setEmail('test@example.com');

        $this->userManager->expects($this->never())
            ->method('findUserByEmail');

        $this->violation->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($user, $this->constraint);
    }

    /**
     * @param int|null $id
     * @return User
     */
    protected function getUser($id = null)
    {
        $user = new User();

        if (null !== $id) {
            $user->setId($id);
        }

        return $user;
    }
}
