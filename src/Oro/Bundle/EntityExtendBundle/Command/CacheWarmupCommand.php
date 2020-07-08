<?php

namespace Oro\Bundle\EntityExtendBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The CLI command to warming up caches related to extended entities.
 */
class CacheWarmupCommand extends CacheCommand
{
    /** @var string */
    protected static $defaultName = 'oro:entity-extend:cache:warmup';

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setDescription('Warms up extended entity cache.')
            ->addOption(
                'cache-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'The cache directory'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Warm up extended entity cache.');

        $this->cacheDir = $input->getOption('cache-dir');

        $this->warmup($output);
    }
}
