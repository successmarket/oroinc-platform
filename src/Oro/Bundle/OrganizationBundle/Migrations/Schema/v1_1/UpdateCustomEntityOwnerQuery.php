<?php

namespace Oro\Bundle\OrganizationBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigModel;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

class UpdateCustomEntityOwnerQuery extends ParametrizedMigrationQuery
{
    const NEW_OWNER_LABEL = 'oro.custom_entity.owner.label';
    const NEW_OWNER_DESCRIPTION = 'oro.custom_entity.owner.description';

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $this->migrateConfigs($logger, true);

        return $logger->getMessages();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(LoggerInterface $logger)
    {
        $this->migrateConfigs($logger);
    }

    /**
     * @param LoggerInterface $logger
     * @param bool $dryRun
     */
    protected function migrateConfigs(LoggerInterface $logger, $dryRun = false)
    {
        $query = 'SELECT cf.id, c.class_name, cf.data'
            . ' FROM oro_entity_config c'
            . ' INNER JOIN oro_entity_config_field cf ON cf.entity_id = c.id'
            . ' WHERE cf.field_name = :field';
        $params = ['field' => 'owner'];
        $types = ['field' => Types::STRING];

        $this->logQuery($logger, $query, $params, $types);

        $updateQueries = [];

        // prepare update queries
        $rows = $this->connection->fetchAll($query, $params, $types);
        foreach ($rows as $row) {
            if (strpos($row['class_name'], 'Extend\\Entity\\') !== 0) {
                continue;
            }
            $data = $this->connection->convertToPHPValue($row['data'], 'array');
            if (isset($data['entity']['label']) && $data['entity']['label'] === 'Owner') {
                $data['entity']['label'] = self::NEW_OWNER_LABEL;
                $data['entity']['description'] = self::NEW_OWNER_DESCRIPTION;

                $id = $row['id'];

                $updateQueries[] = [
                    'UPDATE oro_entity_config_field SET data = :data, mode = :mode WHERE id = :id',
                    ['id' => $id, 'mode' => ConfigModel::MODE_DEFAULT, 'data' => $data],
                    ['id' => Types::INTEGER, 'mode' => Types::STRING, 'data' => Types::ARRAY]
                ];
                $updateQueries[] = [
                    'UPDATE oro_entity_config_index_value SET value = :value'
                    . ' WHERE field_id = :id AND scope = :scope AND code = :code',
                    ['id' => $id, 'scope' => 'entity', 'code' => 'label', 'value' => self::NEW_OWNER_LABEL],
                    [
                        'id' => Types::INTEGER,
                        'scope' => Types::STRING,
                        'code' => Types::STRING,
                        'value' => Types::STRING
                    ]
                ];
            }
        }

        // execute update queries
        foreach ($updateQueries as $val) {
            $this->logQuery($logger, $val[0], $val[1], $val[2]);
            if (!$dryRun) {
                $this->connection->executeUpdate($val[0], $val[1], $val[2]);
            }
        }
    }
}
