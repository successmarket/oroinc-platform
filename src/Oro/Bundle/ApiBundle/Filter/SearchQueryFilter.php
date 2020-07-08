<?php

namespace Oro\Bundle\ApiBundle\Filter;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Oro\Bundle\ApiBundle\Exception\InvalidFilterException;
use Oro\Bundle\SearchBundle\Exception\ExpressionSyntaxError;
use Oro\Bundle\SearchBundle\Provider\AbstractSearchMappingProvider;
use Oro\Bundle\SearchBundle\Query\Expression\Lexer as SearchQueryLexer;
use Oro\Bundle\SearchBundle\Query\Expression\Parser as SearchQueryParser;
use Oro\Bundle\SearchBundle\Query\Query as SearchQuery;

/**
 * A filter that can be used to filter data by a query for a search index.
 */
class SearchQueryFilter extends StandaloneFilter implements FieldFilterInterface
{
    /** @var AbstractSearchMappingProvider */
    private $searchMappingProvider;

    /** @var SearchFieldResolverFactory */
    private $searchFieldResolverFactory;

    /** @var ExpressionVisitor|null */
    private $searchQueryCriteriaVisitor;

    /** @var string */
    private $entityClass;

    /** @var array [field name => field name in search index, ...] */
    private $fieldMappings = [];

    /**
     * @param AbstractSearchMappingProvider $searchMappingProvider
     */
    public function setSearchMappingProvider(AbstractSearchMappingProvider $searchMappingProvider): void
    {
        $this->searchMappingProvider = $searchMappingProvider;
    }

    /**
     * @param SearchFieldResolverFactory $searchFieldResolverFactory
     */
    public function setSearchFieldResolverFactory(SearchFieldResolverFactory $searchFieldResolverFactory): void
    {
        $this->searchFieldResolverFactory = $searchFieldResolverFactory;
    }

    /**
     * @param ExpressionVisitor $searchQueryCriteriaVisitor
     */
    public function setSearchQueryCriteriaVisitor(ExpressionVisitor $searchQueryCriteriaVisitor): void
    {
        $this->searchQueryCriteriaVisitor = $searchQueryCriteriaVisitor;
    }

    /**
     * @param string $entityClass
     */
    public function setEntityClass(string $entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    /**
     * @param array $fieldMappings [field name => field name in search index, ...]
     */
    public function setFieldMappings(array $fieldMappings): void
    {
        $this->fieldMappings = $fieldMappings;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Criteria $criteria, FilterValue $value = null): void
    {
        $expr = $this->createExpression($value);
        if (null !== $expr) {
            $criteria->andWhere($expr);
        }
    }

    /**
     * @param FilterValue|null $value
     *
     * @return Expression|null
     */
    private function createExpression(?FilterValue $value): ?Expression
    {
        if (null === $value) {
            return null;
        }

        if (!$this->entityClass) {
            throw new \InvalidArgumentException('The entity class must not be empty.');
        }

        $expr = $this->getSearchQuery($value->getValue())
            ->getCriteria()
            ->getWhereExpression();
        if (null !== $expr && null !== $this->searchQueryCriteriaVisitor) {
            $expr = $this->searchQueryCriteriaVisitor->dispatch($expr);
        }

        return $expr;
    }

    /**
     * @param string $whereExpression
     *
     * @return SearchQuery
     */
    private function getSearchQuery(string $whereExpression): SearchQuery
    {
        $lexer = new SearchQueryLexer();
        $parser = new SearchQueryParser();
        $mapping = $this->searchMappingProvider->getEntityConfig($this->entityClass);
        $query = (new SearchQuery())->from($mapping['alias']);
        $fieldResolver = $this->searchFieldResolverFactory->createFieldResolver(
            $this->entityClass,
            $this->fieldMappings
        );

        try {
            return $parser->parse(
                $lexer->tokenize($whereExpression),
                $query,
                $fieldResolver,
                SearchQuery::KEYWORD_WHERE
            );
        } catch (ExpressionSyntaxError $e) {
            throw new InvalidFilterException($e->getMessage());
        }
    }
}
