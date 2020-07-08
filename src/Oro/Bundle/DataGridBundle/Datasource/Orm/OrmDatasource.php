<?php

namespace Oro\Bundle\DataGridBundle\Datasource\Orm;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\Configs\ConfigProcessorInterface;
use Oro\Bundle\DataGridBundle\Datasource\ParameterBinderAwareInterface;
use Oro\Bundle\DataGridBundle\Datasource\ParameterBinderInterface;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecordInterface;
use Oro\Bundle\DataGridBundle\Event\OrmResultAfter;
use Oro\Bundle\DataGridBundle\Event\OrmResultBefore;
use Oro\Bundle\DataGridBundle\Event\OrmResultBeforeQuery;
use Oro\Bundle\DataGridBundle\Exception\BadMethodCallException;
use Oro\Component\DoctrineUtils\ORM\QueryHintResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Allows to create datagrids from ORM queries.
 */
class OrmDatasource implements DatasourceInterface, ParameterBinderAwareInterface
{
    const TYPE = 'orm';

    /** @var QueryBuilder */
    protected $qb;

    /** @var QueryBuilder */
    protected $countQb;

    /** @var array|null */
    protected $queryHints;

    /** @var array */
    protected $countQueryHints = [];

    /** @var ConfigProcessorInterface */
    protected $configProcessor;

    /** @var DatagridInterface */
    protected $datagrid;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var ParameterBinderInterface */
    protected $parameterBinder;

    /** @var QueryHintResolver */
    protected $queryHintResolver;

    /** @var QueryExecutorInterface */
    private $queryExecutor;

    /**
     * @param ConfigProcessorInterface $processor
     * @param EventDispatcherInterface $eventDispatcher
     * @param ParameterBinderInterface $parameterBinder
     * @param QueryHintResolver        $queryHintResolver
     * @param QueryExecutorInterface   $queryExecutor
     */
    public function __construct(
        ConfigProcessorInterface $processor,
        EventDispatcherInterface $eventDispatcher,
        ParameterBinderInterface $parameterBinder,
        QueryHintResolver $queryHintResolver,
        QueryExecutorInterface $queryExecutor
    ) {
        $this->configProcessor = $processor;
        $this->eventDispatcher = $eventDispatcher;
        $this->parameterBinder = $parameterBinder;
        $this->queryHintResolver = $queryHintResolver;
        $this->queryExecutor = $queryExecutor;
    }

    /**
     * {@inheritDoc}
     */
    public function process(DatagridInterface $grid, array $config)
    {
        $this->datagrid = $grid;
        $this->processConfigs($config);
        $grid->setDatasource(clone $this);
    }

    /**
     * You must avoid to make changes of QueryBuilder here
     * because query was already used as is in datagrid extensions for example "PaginatorExtension"
     *
     * @return ResultRecordInterface[]
     */
    public function getResults()
    {
        $this->eventDispatcher->dispatch(
            OrmResultBeforeQuery::NAME,
            new OrmResultBeforeQuery($this->datagrid, $this->qb)
        );

        $query = $this->qb->getQuery();
        $this->queryHintResolver->resolveHints($query, $this->queryHints ?? []);

        $this->eventDispatcher->dispatch(
            OrmResultBefore::NAME,
            new OrmResultBefore($this->datagrid, $query)
        );

        $rows = $this->queryExecutor->execute($this->datagrid, $query);
        $records = [];
        foreach ($rows as $row) {
            $records[] = new ResultRecord($row);
        }

        $event = new OrmResultAfter($this->datagrid, $records, $query);
        $this->eventDispatcher->dispatch(OrmResultAfter::NAME, $event);

        return $event->getRecords();
    }

    /**
     * Gets datagrid this datasource belongs to.
     *
     * @return DatagridInterface
     */
    public function getDatagrid(): DatagridInterface
    {
        return $this->datagrid;
    }

    /**
     * Returns QueryBuilder for count query if it was set
     *
     * @return QueryBuilder|null
     */
    public function getCountQb()
    {
        return $this->countQb;
    }

    /**
     * @return array
     */
    public function getCountQueryHints()
    {
        return $this->countQueryHints;
    }

    /**
     * Returns query builder
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }

    public function getQueryHints(): ?array
    {
        return $this->queryHints;
    }

    /**
     * Set QueryBuilder
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    public function setQueryBuilder(QueryBuilder $qb)
    {
        $this->qb = $qb;

        return $this;
    }

    /**
     * {@inheritdoc}
     *  @deprecated since 2.0.
     */
    public function getParameterBinder()
    {
        return $this->parameterBinder;
    }

    /**
     * {@inheritdoc}
     */
    public function bindParameters(array $datasourceToDatagridParameters, $append = true)
    {
        if (!$this->datagrid) {
            throw new BadMethodCallException('Method is not allowed when datasource is not processed.');
        }

        return $this->parameterBinder->bindParameters($this->datagrid, $datasourceToDatagridParameters, $append);
    }

    public function __clone()
    {
        $this->qb      = clone $this->qb;
        $this->countQb = $this->countQb ? clone $this->countQb : null;
    }

    /**
     * @param array $config
     */
    protected function processConfigs(array $config)
    {
        $this->qb = $this->configProcessor->processQuery($config);
        $this->countQb = $this->configProcessor->processCountQuery($config);

        $this->queryHints = $config['hints'] ?? [];
        $this->countQueryHints = $config['count_hints'] ?? $this->queryHints;
    }
}
