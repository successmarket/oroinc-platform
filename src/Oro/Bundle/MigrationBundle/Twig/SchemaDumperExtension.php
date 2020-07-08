<?php

namespace Oro\Bundle\MigrationBundle\Twig;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides a Twig function used in generator of data migration classes:
 *   - oro_migration_get_schema_column_options
 */
class SchemaDumperExtension extends AbstractExtension implements ServiceSubscriberInterface
{
    /** @var ContainerInterface */
    private $container;

    /** @var AbstractPlatform */
    protected $platform;

    /** @var Column */
    protected $defaultColumn;

    /** @var array */
    protected $defaultColumnOptions = [];

    /** @var array */
    protected $optionNames = [
        'default',
        'notnull',
        'length',
        'precision',
        'scale',
        'fixed',
        'unsigned',
        'autoincrement'
    ];

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'schema_dumper_extension';
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('oro_migration_get_schema_column_options', [$this, 'getColumnOptions']),
        ];
    }

    /**
     * @param Column $column
     * @return array
     */
    public function getColumnOptions(Column $column)
    {
        $defaultOptions = $this->getDefaultOptions();
        $platform = $this->getPlatform();
        $options = [];

        foreach ($this->optionNames as $optionName) {
            $value = $this->getColumnOption($column, $optionName);
            if ($value !== $defaultOptions[$optionName]) {
                $options[$optionName] = $value;
            }
        }

        $comment = $column->getComment();
        if ($platform && $platform->isCommentedDoctrineType($column->getType())) {
            $comment .= $platform->getDoctrineTypeComment($column->getType());
        }
        if (!empty($comment)) {
            $options['comment'] = $comment;
        }

        return $options;
    }

    /**
     * @param Column $column
     * @param string $optionName
     * @return mixed
     */
    protected function getColumnOption(Column $column, $optionName)
    {
        $method = 'get' . $optionName;

        return $column->$method();
    }

    /**
     * @return AbstractPlatform
     */
    protected function getPlatform()
    {
        if (!$this->platform) {
            $this->platform = $this->container->get('doctrine')
                ->getConnection()
                ->getDatabasePlatform();
        }

        return $this->platform;
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        if (!$this->defaultColumn) {
            $this->defaultColumn = new Column('_template_', Type::getType(Types::STRING));
        }
        if (!$this->defaultColumnOptions) {
            foreach ($this->optionNames as $optionName) {
                $this->defaultColumnOptions[$optionName] = $this->getColumnOption($this->defaultColumn, $optionName);
            }
        }

        return $this->defaultColumnOptions;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return [
            'doctrine' => ManagerRegistry::class,
        ];
    }
}
