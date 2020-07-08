<?php

namespace Oro\Component\MessageQueue\Job\Extension;

use Oro\Component\MessageQueue\Job\Job;

/**
 * MQ job extension that contains all job extensions and process them.
 */
class ChainExtension implements ExtensionInterface
{
    /** @var iterable|ExtensionInterface[] */
    private $extensions;

    /**
     * @param iterable|ExtensionInterface[] $extensions
     */
    public function __construct(iterable $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * {@inheritdoc}
     */
    public function onPreRunUnique(Job $job)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPreRunUnique($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onPostRunUnique(Job $job, $jobResult)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPostRunUnique($job, $jobResult);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onPreCreateDelayed(Job $job)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPreCreateDelayed($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onPostCreateDelayed(Job $job, $createResult)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPostCreateDelayed($job, $createResult);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onPreRunDelayed(Job $job)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPreRunDelayed($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onPostRunDelayed(Job $job, $jobResult)
    {
        foreach ($this->extensions as $extension) {
            $extension->onPostRunDelayed($job, $jobResult);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onCancel(Job $job)
    {
        foreach ($this->extensions as $extension) {
            $extension->onCancel($job);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onError(Job $job)
    {
        foreach ($this->extensions as $extension) {
            $extension->onError($job);
        }
    }
}
