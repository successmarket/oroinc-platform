<?php

namespace Oro\Bundle\ImportExportBundle;

use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\AddNormalizerCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\ContextAggregatorCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\FormatterProviderPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\IdentityValidationLoaderPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\ImportExportConfigurationRegistryCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\ProcessorRegistryCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\ReaderCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\TemplateEntityRepositoryCompilerPass;
use Oro\Bundle\ImportExportBundle\DependencyInjection\Compiler\WriterCompilerPass;
use Oro\Bundle\ImportExportBundle\MimeType\CsvMimeTypeGuesser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * ImportExport Bundle. Registers compiler passes. Adds MIME Type guesser.
 */
class OroImportExportBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new IdentityValidationLoaderPass());
        $container->addCompilerPass(new AddNormalizerCompilerPass());
        $container->addCompilerPass(new ProcessorRegistryCompilerPass());
        $container->addCompilerPass(new TemplateEntityRepositoryCompilerPass());
        $container->addCompilerPass(new FormatterProviderPass());
        $container->addCompilerPass(new WriterCompilerPass());
        $container->addCompilerPass(new ReaderCompilerPass());
        $container->addCompilerPass(new ContextAggregatorCompilerPass());
        $container->addCompilerPass(new ImportExportConfigurationRegistryCompilerPass());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        MimeTypeGuesser::getInstance()->register(new CsvMimeTypeGuesser());
    }
}
