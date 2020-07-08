<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Security\Http\Firewall;

use Oro\Bundle\ApiBundle\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;

class ExceptionListenerTest extends \PHPUnit\Framework\TestCase
{
    private const SESSION_NAME = 'TEST_SESSION_ID';

    /**
     * @param \Exception $exception
     *
     * @dataProvider getExceptionProvider
     */
    public function testSetTargetPathShouldCallParentWithCookie(\Exception $exception)
    {
        $event = $this->createEvent($exception);
        $event->getRequest()->cookies->add([self::SESSION_NAME => 'o595fqdg5214u4e4nfcs3uc923']);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('getName')
            ->willReturn(self::SESSION_NAME);
        $session->expects(self::once())
            ->method('set')
            ->with('_security.key.target_path', 'http://localhost/');
        $event->getRequest()->setSession($session);

        $listener = $this->createExceptionListener(true);
        $listener->onKernelException($event);
    }

    /**
     * @param \Exception $exception
     *
     * @dataProvider getExceptionProvider
     */
    public function testSetTargetPathShouldNotCallParentWithoutCookie(\Exception $exception)
    {
        $event = $this->createEvent($exception);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())
            ->method('getName')
            ->willReturn(self::SESSION_NAME);
        $session->expects(self::never())->method('set');
        $event->getRequest()->setSession($session);

        $listener = $this->createExceptionListener(true);
        $listener->onKernelException($event);
    }

    public function getExceptionProvider()
    {
        return [
            [new AccessDeniedException()],
            [new \LogicException('random', 0, new AccessDeniedException('embed', new AuthenticationException()))],
            [new AccessDeniedException('random', new \LogicException())]
        ];
    }

    /**
     * @param bool $fullSetup
     *
     * @return ExceptionListener
     */
    protected function createExceptionListener($fullSetup = false)
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $trustResolver = $this->createMock(AuthenticationTrustResolverInterface::class);
        $authenticationEntryPoint = null;

        if ($fullSetup) {
            $tokenStorage->expects(self::once())
                ->method('getToken')
                ->willReturn($this->createMock(TokenInterface::class));
            $trustResolver->expects(self::once())
                ->method('isFullFledged')
                ->willReturn(false);
            $authenticationEntryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
            $authenticationEntryPoint->expects(self::once())
                ->method('start')
                ->willReturn(new Response('OK'));
        }

        return new ExceptionListener(
            $tokenStorage,
            $trustResolver,
            $this->createMock(HttpUtils::class),
            'key',
            $authenticationEntryPoint,
            null,
            null
        );
    }

    /**
     * @param \Exception $exception
     *
     * @return GetResponseForExceptionEvent
     */
    private function createEvent(\Exception $exception)
    {
        return new GetResponseForExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MASTER_REQUEST,
            $exception
        );
    }
}
