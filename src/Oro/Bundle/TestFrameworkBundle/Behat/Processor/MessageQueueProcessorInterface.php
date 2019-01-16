<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Processor;

/**
 * Message queue processor interface that helps start, stop and process consumer or clean queue
 */
interface MessageQueueProcessorInterface
{
    // Make timeout configurable via command line arguments? See BAP-18237
    const TIMEOUT = 600;

    /**
     * @return void
     */
    public function startMessageQueue();

    /**
     * @return void
     */
    public function stopMessageQueue();

    /**
     * @param int $timeLimit Limit queue processing, seconds
     *
     * @return void
     */
    public function waitWhileProcessingMessages($timeLimit = self::TIMEOUT);

    /**
     * @return void
     */
    public function cleanUp();

    /**
     * @return boolean
     */
    public function isRunning();
}
