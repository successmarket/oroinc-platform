<?php

namespace Oro\Bundle\BatchBundle\Monolog\Handler;

use Akeneo\Bundle\BatchBundle\Monolog\Handler\BatchLogHandler as AkeneoBatchLogHandler;
use Monolog\Logger;

/**
 * Write the log into a separate log file
 */
class BatchLogHandler extends AkeneoBatchLogHandler
{
    /** @var bool */
    protected $isActive = true;

    /**
     * {@inheritDoc}
     */
    public function __construct($logDir)
    {
        $this->logDir = $logDir;

        $this->filePermission = null;
        $this->useLocking = false;

        $this->setLevel(Logger::WARNING);
        $this->bubble = true;
    }

    /**
     * @param boolean $isActive
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $record)
    {
        if ($this->isActive()) {
            parent::write($record);
        }
    }
}
