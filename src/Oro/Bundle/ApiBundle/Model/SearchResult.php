<?php

namespace Oro\Bundle\ApiBundle\Model;

use Doctrine\DBAL\Exception\DriverException;
use Oro\Bundle\ApiBundle\Exception\InvalidSearchQueryException;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\SearchBundle\Query\Result;
use Oro\Bundle\SearchBundle\Query\SearchQueryInterface;

/**
 * Represents a search query result.
 */
class SearchResult
{
    /** @var SearchQueryInterface */
    private $query;

    /** @var bool */
    private $hasMore;

    /** @var int|null */
    private $limit;

    /** @var Result|null */
    private $searchResult;

    /**
     * @param SearchQueryInterface $query
     * @param bool                 $hasMore Indicates whether an additional record with
     *                                      key "_" {@see \Oro\Component\EntitySerializer\ConfigUtil::INFO_RECORD_KEY}
     *                                      and value ['has_more' => true]
     *                                      {@see \Oro\Component\EntitySerializer\ConfigUtil::HAS_MORE}
     *                                      should be added to the collection of records
     *                                      if the search index has more records than it was requested.
     */
    public function __construct(SearchQueryInterface $query, bool $hasMore = false)
    {
        $this->query = $query;
        $this->hasMore = $hasMore;
    }

    /**
     * Gets search query result records.
     *
     * @return Result\Item[]
     */
    public function getRecords(): array
    {
        return $this->execute(function () {
            $records = $this->getSearchResult()->getElements();
            if (null !== $this->limit && \count($records) > $this->limit) {
                $records = \array_slice($records, 0, $this->limit);
                $records[ConfigUtil::INFO_RECORD_KEY] = [ConfigUtil::HAS_MORE => true];
            }

            return $records;
        });
    }

    /**
     * Gets the number of search query result records without limit parameters.
     *
     * @return int
     */
    public function getRecordsCount(): int
    {
        return $this->execute(function () {
            return $this->getSearchResult()->getRecordsCount();
        });
    }

    /**
     * Gets aggregated data collected when execution the query.
     * Format for the "count" function: [aggregating name => ['value' => field value, 'count' => count value], ...]
     * Format for mathematical functions: [aggregating name => aggregated value, ...]
     *
     * @return array
     */
    public function getAggregatedData(): array
    {
        return $this->execute(function () {
            return $this->normalizeAggregatedData(
                $this->getSearchResult()->getAggregatedData()
            );
        });
    }

    /**
     * @return Result
     */
    private function getSearchResult(): Result
    {
        if (null === $this->searchResult) {
            if ($this->hasMore) {
                $this->limit = $this->query->getMaxResults();
                if (null !== $this->limit) {
                    $this->query = clone $this->query;
                    $this->query->setMaxResults($this->limit + 1);
                }
            }
            $this->searchResult = $this->query->getResult();
        }

        return $this->searchResult;
    }

    /**
     * @param callable $callback
     *
     * @return mixed
     */
    private function execute($callback)
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            if ($e instanceof DriverException
                || (
                    class_exists('Elasticsearch\Common\Exceptions\BadRequest400Exception')
                    && $e instanceof \Elasticsearch\Common\Exceptions\BadRequest400Exception
                )
            ) {
                throw new InvalidSearchQueryException('Invalid search query.', $e->getCode(), $e);
            }

            throw $e;
        }
    }

    /**
     * @param array $aggregatedData
     *
     * @return array
     */
    private function normalizeAggregatedData(array $aggregatedData): array
    {
        $result = [];
        foreach ($aggregatedData as $name => $value) {
            if (\is_array($value)) {
                // "count" aggregation
                $resultValue = [];
                foreach ($value as $key => $val) {
                    $resultValue[] = ['value' => $key, 'count' => $val];
                }
                $value = $resultValue;
            }
            $result[$name] = $value;
        }

        return $result;
    }
}
