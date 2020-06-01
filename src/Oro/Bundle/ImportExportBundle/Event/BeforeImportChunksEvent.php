<?php

namespace Oro\Bundle\ImportExportBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event is dispatched in the PreImportMessageProcessorAbstract before the jobs for the import chunks are created
 */
class BeforeImportChunksEvent extends Event
{
    /** @var array */
    private $body;

    /**
     * @param array $body
     */
    public function __construct(array $body)
    {
        $this->body = $body;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->body;
    }
}
