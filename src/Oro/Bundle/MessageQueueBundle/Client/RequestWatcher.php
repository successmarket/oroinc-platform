<?php

namespace Oro\Bundle\MessageQueueBundle\Client;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Watches HTTP requests in order to enable the buffering mode at the beginning of the master request handling
 * and send all collected messages at the ending of the master request handling.
 */
class RequestWatcher implements EventSubscriberInterface
{
    /** @var BufferedMessageProducer */
    private $producer;

    /**
     * @param BufferedMessageProducer $producer
     */
    public function __construct(BufferedMessageProducer $producer)
    {
        $this->producer = $producer;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onRequestStart', -250],
            KernelEvents::TERMINATE => ['onRequestEnd', -125]
        ];
    }

    /**
     * @param RequestEvent $event
     */
    public function onRequestStart(RequestEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if (!$this->producer->isBufferingEnabled()) {
            $this->producer->enableBuffering();
        }
    }

    /**
     * @param TerminateEvent $event
     */
    public function onRequestEnd(TerminateEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if ($this->producer->isBufferingEnabled() && $this->producer->hasBufferedMessages()) {
            $this->producer->flushBuffer();
        }
    }
}
