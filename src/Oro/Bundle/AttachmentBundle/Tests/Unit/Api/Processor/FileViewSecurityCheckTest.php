<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Api\Processor;

use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Get\GetProcessorTestCase;
use Oro\Bundle\AttachmentBundle\Api\Processor\FileViewSecurityCheck;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FileViewSecurityCheckTest extends GetProcessorTestCase
{
    /** @var AuthorizationCheckerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $authorizationChecker;

    /** @var FileViewSecurityCheck */
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $this->processor = new FileViewSecurityCheck($this->authorizationChecker);
    }

    public function testProcessWhenAccessGranted()
    {
        $fileClass = File::class;
        $fileId = 123;

        $this->authorizationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('VIEW', new ObjectIdentity($fileId, $fileClass))
            ->willReturn(true);

        $this->context->setClassName($fileClass);
        $this->context->setId($fileId);
        $this->processor->process($this->context);
    }

    public function testProcessWhenAccessDenied()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('No access to the entity.');

        $fileClass = File::class;
        $fileId = 123;

        $this->authorizationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('VIEW', new ObjectIdentity($fileId, $fileClass))
            ->willReturn(false);

        $this->context->setClassName($fileClass);
        $this->context->setId($fileId);
        $this->processor->process($this->context);
    }
}
