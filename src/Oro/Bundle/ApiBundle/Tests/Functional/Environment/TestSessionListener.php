<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\Environment;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This listener is executed only in test environment and it is required
 * to be able to check whether the session was started in API request.
 */
class TestSessionListener implements EventSubscriberInterface
{
    /** @var bool */
    private $isSessionStarted = false;

    /**
     * Indicates whether the session was started in the current request.
     *
     * @return bool
     */
    public function isSessionStarted(): bool
    {
        return $this->isSessionStarted;
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        if (null === $session) {
            throw new \LogicException(sprintf(
                'The Session is not initialized. Check the priority of %s::onKernelRequest().',
                __CLASS__
            ));
        }

        $this->isSessionStarted = false;
        if ($session->isStarted()) {
            $session->save();
        }
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $this->isSessionStarted = $session && $session->isStarted();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            /**
             * must be executed after Symfony's TestSessionListener::onKernelRequest,
             * because Symfony's listener initializes the session
             * @see \Symfony\Component\HttpKernel\EventListener\SessionListener::onKernelResponse
             */
            KernelEvents::REQUEST  => ['onKernelRequest', 127],
            /**
             * must be executed before Symfony's TestSessionListener::onKernelResponse,
             * because we need to check whether the session was started before it is closed by Symfony's listener
             * @see \Symfony\Component\HttpKernel\EventListener\TestSessionListener::onKernelResponse
             */
            KernelEvents::RESPONSE => ['onKernelResponse', -127]
        ];
    }
}
