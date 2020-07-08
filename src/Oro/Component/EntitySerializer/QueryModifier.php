<?php

namespace Oro\Component\EntitySerializer;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Oro\Component\PhpUtils\ReflectionUtil;

/**
 * Provides a set of methods to prepare a query to load data.
 */
class QueryModifier
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /** @var FieldAccessor */
    private $fieldAccessor;

    /** @var ConfigAccessor */
    private $configAccessor;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param FieldAccessor  $fieldAccessor
     * @param ConfigAccessor $configAccessor
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        FieldAccessor $fieldAccessor,
        ConfigAccessor $configAccessor
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->fieldAccessor = $fieldAccessor;
        $this->configAccessor = $configAccessor;
    }

    /**
     * @param QueryBuilder $qb
     * @param EntityConfig $config
     */
    public function updateQuery(QueryBuilder $qb, EntityConfig $config): void
    {
        $rootAlias = $this->doctrineHelper->getRootAlias($qb);
        $entityClass = $this->doctrineHelper->getRootEntityClass($qb);

        $qb->resetDQLPart('select');
        $this->updateSelectQueryPart($qb, $rootAlias, $entityClass, $config);

        $isForcePartialLoadEnabled =
            $config->isPartialLoadEnabled()
            && ForcePartialLoadHintUtil::isForcePartialLoadHintEnabled($config);
        $needToDisableForcePartialLoadHint = false;
        $innerJoinAssociations = $this->getAssociationMap($config->getInnerJoinAssociations());
        $this->updateJoinQueryPart(
            $qb,
            $rootAlias,
            $entityClass,
            $config,
            $isForcePartialLoadEnabled,
            $needToDisableForcePartialLoadHint,
            $innerJoinAssociations
        );
        if ($needToDisableForcePartialLoadHint) {
            ForcePartialLoadHintUtil::disableForcePartialLoadHint($config);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $alias
     * @param string       $entityClass
     * @param EntityConfig $config
     * @param bool         $withAssociations
     */
    public function updateSelectQueryPart(
        QueryBuilder $qb,
        $alias,
        $entityClass,
        EntityConfig $config,
        $withAssociations = false
    ): void {
        if ($config->isPartialLoadEnabled()) {
            $fields = $this->fieldAccessor->getFieldsToSelect($entityClass, $config, $withAssociations);
            $qb->addSelect(sprintf('partial %s.{%s}', $alias, implode(',', $fields)));
        } else {
            $qb->addSelect($alias);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $rootAlias
     * @param string       $entityClass
     * @param EntityConfig $config
     * @param bool         $isForcePartialLoadEnabled
     * @param bool         $needToDisableForcePartialLoadHint
     * @param array        $innerJoinAssociationMap
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function updateJoinQueryPart(
        QueryBuilder $qb,
        string $rootAlias,
        string $entityClass,
        EntityConfig $config,
        bool $isForcePartialLoadEnabled,
        bool &$needToDisableForcePartialLoadHint,
        array $innerJoinAssociationMap
    ): void {
        $aliasCounter = 0;
        $entityMetadata = $this->doctrineHelper->getEntityMetadata($entityClass);
        $innerJoinSingleValuedAssociations = [];
        $fields = $this->fieldAccessor->getFields($entityClass, $config);
        foreach ($fields as $field) {
            $property = $this->configAccessor->getPropertyPath($field, $config->getField($field));
            if (!$entityMetadata->isSingleValuedAssociation($property)) {
                continue;
            }

            $isInnerJoin =
                !$needToDisableForcePartialLoadHint
                && $innerJoinAssociationMap
                && isset($innerJoinAssociationMap[$property]);
            $joinExpr = $rootAlias . '.' . $property;
            $join = $this->getExistingJoin($qb, $rootAlias, $joinExpr);
            if (null === $join) {
                $alias = 'a' . ++$aliasCounter;
                if ($isInnerJoin) {
                    $qb->innerJoin($joinExpr, $alias);
                } else {
                    $qb->leftJoin($joinExpr, $alias);
                }
            } else {
                $alias = $join->getAlias();
                if ($isInnerJoin) {
                    $this->ensureInnerJoin($join);
                }
            }
            $targetEntityClass = $entityMetadata->getAssociationTargetClass($property);
            $targetConfig = $this->configAccessor->getTargetEntity($config, $field);
            $this->updateSelectQueryPart($qb, $alias, $targetEntityClass, $targetConfig, true);
            if ($isForcePartialLoadEnabled
                && !$needToDisableForcePartialLoadHint
                && $this->hasLeftJoinSingleValuedAssociations(
                    $targetEntityClass,
                    $targetConfig,
                    $property,
                    $innerJoinAssociationMap
                )
            ) {
                $needToDisableForcePartialLoadHint = true;
            }
            if (!$needToDisableForcePartialLoadHint) {
                $innerJoinSingleValuedAssociations[] = [$alias, $targetEntityClass, $targetConfig];
            }
        }
        if ($isForcePartialLoadEnabled && !$needToDisableForcePartialLoadHint && $innerJoinSingleValuedAssociations) {
            foreach ($innerJoinSingleValuedAssociations as [$targetAlias, $targetEntityClass, $targetConfig]) {
                $aliasCounter = $this->updateJoinQueryPartForNestedAssociations(
                    $qb,
                    $targetAlias,
                    $targetEntityClass,
                    $targetConfig,
                    $aliasCounter
                );
            }
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $rootAlias
     * @param string       $entityClass
     * @param EntityConfig $config
     * @param int          $aliasCounter
     *
     * @return int
     */
    private function updateJoinQueryPartForNestedAssociations(
        QueryBuilder $qb,
        string $rootAlias,
        string $entityClass,
        EntityConfig $config,
        int $aliasCounter
    ): int {
        $entityMetadata = $this->doctrineHelper->getEntityMetadata($entityClass);
        $fields = $this->fieldAccessor->getFields($entityClass, $config);
        foreach ($fields as $field) {
            $property = $this->configAccessor->getPropertyPath($field, $config->getField($field));
            if (!$entityMetadata->isSingleValuedAssociation($property)) {
                continue;
            }

            $joinExpr = $rootAlias . '.' . $property;
            $join = $this->getExistingJoin($qb, $rootAlias, $joinExpr);
            if (null === $join) {
                $alias = 'a' . ++$aliasCounter;
                $qb->innerJoin($joinExpr, $alias);
            } else {
                $alias = $join->getAlias();
                $this->ensureInnerJoin($join);
            }
            $this->updateSelectQueryPart(
                $qb,
                $alias,
                $entityMetadata->getAssociationTargetClass($property),
                $this->configAccessor->getTargetEntity($config, $field),
                true
            );
        }

        return $aliasCounter;
    }

    /**
     * @param string[] $associations [property path, ...]
     *
     * @return array [property path => true, ...]
     */
    private function getAssociationMap(array $associations): array
    {
        $associationMap = [];
        foreach ($associations as $propertyPath) {
            $delimiterPos = strpos($propertyPath, ConfigUtil::PATH_DELIMITER);
            while (false !== $delimiterPos) {
                /** @var string $parentPropertyPath */
                $parentPropertyPath = substr($propertyPath, 0, $delimiterPos);
                if (!isset($associationMap[$parentPropertyPath])) {
                    $associationMap[$parentPropertyPath] = true;
                }
                $delimiterPos = strpos($propertyPath, ConfigUtil::PATH_DELIMITER, $delimiterPos + 1);
            }
            if (!isset($associationMap[$propertyPath])) {
                $associationMap[$propertyPath] = true;
            }
        }

        return $associationMap;
    }

    /**
     * @param string       $entityClass
     * @param EntityConfig $config
     * @param string       $parentAssociationName
     * @param array        $innerJoinAssociationMap
     *
     * @return bool
     */
    private function hasLeftJoinSingleValuedAssociations(
        string $entityClass,
        EntityConfig $config,
        string $parentAssociationName,
        array $innerJoinAssociationMap
    ): bool {
        $entityMetadata = $this->doctrineHelper->getEntityMetadata($entityClass);
        $fields = $this->fieldAccessor->getFields($entityClass, $config);
        foreach ($fields as $field) {
            $property = $this->configAccessor->getPropertyPath($field, $config->getField($field));
            if (!$entityMetadata->isSingleValuedAssociation($property)) {
                continue;
            }
            $propertyPath = $parentAssociationName . ConfigUtil::PATH_DELIMITER . $property;
            if (!isset($innerJoinAssociationMap[$propertyPath])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $rootAlias
     * @param string       $joinExpr
     *
     * @return Expr\Join|null
     */
    private function getExistingJoin(QueryBuilder $qb, string $rootAlias, string $joinExpr): ?Expr\Join
    {
        $joinParts = $qb->getDQLPart('join');
        if (!empty($joinParts[$rootAlias])) {
            /** @var Expr\Join $join */
            foreach ($joinParts[$rootAlias] as $join) {
                if ($join->getJoin() === $joinExpr) {
                    return $join;
                }
            }
        }

        return null;
    }

    /**
     * @param Expr\Join $join
     */
    private function ensureInnerJoin(Expr\Join $join): void
    {
        if (Expr\Join::INNER_JOIN === $join->getJoinType()) {
            return;
        }

        $joinTypeProperty = ReflectionUtil::getProperty(new \ReflectionClass($join), 'joinType');
        if (null === $joinTypeProperty) {
            throw new \LogicException(sprintf(
                'The "joinType" property does not exist in %s.',
                get_class($join)
            ));
        }
        $joinTypeProperty->setAccessible(true);
        $joinTypeProperty->setValue($join, Expr\Join::INNER_JOIN);
    }
}
