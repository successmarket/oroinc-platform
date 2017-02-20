<?php

namespace Oro\Bundle\EntityExtendBundle\Migrations\Schema\v1_10;

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Migration\UpdateEntityConfigFieldValueQuery;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\Query\AbstractEntityConfigQuery;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class UpdateBidirectionalExtendedRelationsQuery extends AbstractEntityConfigQuery
{
    const LIMIT = 100;

    /**
     * @var QueryBag
     */
    private $queries;

    /**
     * @param QueryBag $queries
     */
    public function __construct(QueryBag $queries)
    {
        $this->queries = $queries;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'Update all extended relations with `bidirectional` option set to true';
    }

    /**
     * {@inheritdoc}
     */
    public function getRowBatchLimit()
    {
        return static::LIMIT;
    }

    /**
     * {@inheritdoc}
     */
    public function processRow(array $row)
    {
        $data = $this->connection->convertToPHPValue($row['data'], 'array');

        // process only extended entities with relations
        if (!$data['extend']['is_extend'] || !isset($data['extend']['relation'])) {
            return;
        }

        foreach ($data['extend']['relation'] as $relation) {
            $bidirectional = true;
            if (!$relation['owner'] || !$relation['target_field_id']) {
                $bidirectional = false;
            }

            /** @var FieldConfigId $fieldConfig */
            $fieldConfig = $relation['field_id'];

            if (!$fieldConfig) {
                continue;
            }

            $fieldConfigFromDb = $this->getEntityConfigFieldFromDb($row['id'], $fieldConfig->getFieldName());
            $fieldData = $this->connection->convertToPHPValue($fieldConfigFromDb['data'], 'array');

            // process only CUSTOM ownership fields
            if ($fieldData['extend']['owner'] !== ExtendScope::OWNER_CUSTOM) {
                continue;
            }

            // if parameter already set, do nothing
            if (isset($fieldData['extend']['bidirectional'])) {
                continue;
            }

            $this->queries->addQuery(
                new UpdateEntityConfigFieldValueQuery(
                    $fieldConfig->getClassName(),
                    $fieldConfig->getFieldName(),
                    'extend',
                    'bidirectional',
                    $bidirectional
                )
            );
        }
    }
}