<?php

namespace Oro\Bundle\MessageQueueBundle\Client;

use Oro\Component\MessageQueue\Consumption\AbstractExtension;
use Oro\Component\MessageQueue\Consumption\Context;

/**
 * Watches MQ processors in order to enable the buffering mode at the beginning of processor executing
 * and send all collected messages at the ending of processor executing.
 */
class ProcessorWatcher extends AbstractExtension
{
    /** @var BufferedMessageProducer */
    private $bufferedProducer;

    /**
     * @param BufferedMessageProducer $bufferedProducer
     */
    public function __construct(BufferedMessageProducer $bufferedProducer)
    {
        $this->bufferedProducer = $bufferedProducer;
    }

    /**
     * @param Context $context
     */
    public function onPreReceived(Context $context): void
    {
        if (!$this->bufferedProducer->isBufferingEnabled()) {
            $this->bufferedProducer->enableBuffering();
        }
    }

    /**
     * @param Context $context
     */
    public function onPostReceived(Context $context): void
    {
        if ($this->bufferedProducer->isBufferingEnabled() && $this->bufferedProducer->hasBufferedMessages()) {
            $this->bufferedProducer->flushBuffer();
        }
    }
}
