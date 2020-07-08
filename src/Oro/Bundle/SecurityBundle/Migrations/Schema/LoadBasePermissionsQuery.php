<?php

namespace Oro\Bundle\SecurityBundle\Migrations\Schema;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedSqlMigrationQuery;
use Psr\Log\LoggerInterface;

class LoadBasePermissionsQuery extends ParametrizedSqlMigrationQuery
{
    /** @var array */
    protected $permissions = [
        'VIEW',
        'CREATE',
        'EDIT',
        'DELETE',
        'ASSIGN'
    ];

    /**
     * {@inheritdoc}
     */
    protected function processQueries(LoggerInterface $logger, $dryRun = false)
    {
        $query = 'INSERT INTO oro_security_permission (name, label, is_apply_to_all, group_names, description) ' .
            'VALUES (:name, :label, :is_apply_to_all, :group_names, :description)';

        $types = [
            'name' => Types::STRING,
            'label' => Types::STRING,
            'is_apply_to_all' => Types::BOOLEAN,
            'group_names' => Types::ARRAY,
            'description' => Types::STRING
        ];

        $permissions = array_diff($this->permissions, $this->getExistingPermissions($logger));

        foreach ($permissions as $permission) {
            $this->addSql(
                $query,
                [
                    'name' => $permission,
                    'label' => $permission,
                    'is_apply_to_all' => true,
                    'group_names' => ['default'],
                    'description' => null
                ],
                $types
            );
        }

        parent::processQueries($logger, $dryRun);
    }

    /**
     * @param LoggerInterface $logger
     * @return array
     */
    protected function getExistingPermissions(LoggerInterface $logger)
    {
        $sql = 'SELECT name FROM oro_security_permission';
        $this->logQuery($logger, $sql);

        return array_column((array)$this->connection->fetchAll($sql), 'name');
    }
}
