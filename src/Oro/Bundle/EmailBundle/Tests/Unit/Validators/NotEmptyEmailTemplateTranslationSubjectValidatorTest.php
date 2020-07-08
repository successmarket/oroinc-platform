<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Validators;

use Oro\Bundle\EmailBundle\Entity\EmailTemplateTranslation;
use Oro\Bundle\EmailBundle\Validator\Constraints\NotEmptyEmailTemplateTranslationSubject;
use Oro\Bundle\EmailBundle\Validator\NotEmptyEmailTemplateTranslationSubjectValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NotEmptyEmailTemplateTranslationSubjectValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new NotEmptyEmailTemplateTranslationSubjectValidator();
    }

    public function testNotSupportedConstraint()
    {
        $value = new EmailTemplateTranslation();
        $constraint = new NotBlank();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($value, $constraint);
    }

    public function testNotSupportedValue()
    {
        $value = 'test';
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($value, $constraint);
    }

    public function testValidateNullNotAllowed()
    {
        $value = null;
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($value, $constraint);
    }

    public function testValidateNullAllowed()
    {
        $value = null;
        $constraint = new NotEmptyEmailTemplateTranslationSubject();
        $constraint->allowNull = true;

        $this->validator->validate($value, $constraint);

        $this->assertEmpty($this->context->getViolations());
    }

    public function testValidateEmptySubject()
    {
        /** @var EmailTemplateTranslation $value */
        $value = new EmailTemplateTranslation();
        $value->setSubject('');
        $value->setSubjectFallback(false);
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->validator->validate($value, $constraint);

        $violations = $this->context->getViolations();
        $this->assertNotEmpty($violations);
        /** @var ConstraintViolation $violation */
        $violation = $violations[0];
        $this->assertEquals('property.path.subject', $violation->getPropertyPath());
        $this->assertEquals('This value should not be blank.', $violation->getMessage());
    }

    public function testValidateEmptySubjectWithFallback()
    {
        /** @var EmailTemplateTranslation $value */
        $value = new EmailTemplateTranslation();
        $value->setSubject('');
        $value->setSubjectFallback(true);
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->validator->validate($value, $constraint);

        $this->assertEmpty($this->context->getViolations());
    }

    public function testValidateNonEmptySubject()
    {
        /** @var EmailTemplateTranslation $value */
        $value = new EmailTemplateTranslation();
        $value->setSubject('Test');
        $value->setSubjectFallback(false);
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->validator->validate($value, $constraint);

        $this->assertEmpty($this->context->getViolations());
    }

    public function testValidateNonEmptySubjectWithFallback()
    {
        /** @var EmailTemplateTranslation $value */
        $value = new EmailTemplateTranslation();
        $value->setSubject('Test');
        $value->setSubjectFallback(true);
        $constraint = new NotEmptyEmailTemplateTranslationSubject();

        $this->validator->validate($value, $constraint);

        $this->assertEmpty($this->context->getViolations());
    }
}
