<?php

namespace Oro\Bundle\SecurityBundle\Migrations\Schema;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

class RemovePermissionGroupNames extends ParametrizedMigrationQuery
{
    /** @var array */
    protected $permissions;

    /** @var array */
    protected $removeGroupNames;

    /**
     * @param array $permissions
     * @param $removeGroupNames
     */
    public function __construct(array $permissions, array $removeGroupNames)
    {
        $this->permissions = $permissions;
        $this->removeGroupNames = $removeGroupNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $this->doExecute($logger, true);

        return $logger->getMessages();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(LoggerInterface $logger)
    {
        $this->doExecute($logger);
    }

    /**
     * @param LoggerInterface $logger
     * @param bool            $dryRun
     */
    protected function doExecute(LoggerInterface $logger, $dryRun = false)
    {
        $scheduledForUpdates = [];
        $permissions = $this->getSharePermissions($logger);

        foreach ($permissions as $permission) {
            $groupNames = $permission['group_names'];
            $changed = false;

            foreach ($this->removeGroupNames as $groupName) {
                if (($key = array_search($groupName, $groupNames, true)) !== false) {
                    unset($groupNames[$key]);

                    $changed = true;
                }
            }

            if ($changed) {
                $scheduledForUpdates[] = [
                    'id' => $permission['id'],
                    'group_names' => $groupNames
                ];
            }
        }

        $this->executeUpdates($scheduledForUpdates, $logger, $dryRun);
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return array
     */
    protected function getSharePermissions(LoggerInterface $logger)
    {
        $sql = 'SELECT id, group_names FROM oro_security_permission WHERE name IN (:permission_name)';
        $params = ['permission_name' => $this->permissions];
        $types = ['permission_name' => Types::SIMPLE_ARRAY];

        $this->logQuery($logger, $sql, $params, $types);

        $result = [];
        $rows = $this->connection->fetchAll($sql, $params, $types);
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row['id'],
                'group_names' => $this->connection->convertToPHPValue($row['group_names'], Types::ARRAY)
            ];
        }

        return $result;
    }

    /**
     * @param array           $rows
     * @param LoggerInterface $logger
     * @param                 $dryRun
     * @throws \Doctrine\DBAL\DBALException
     */
    private function executeUpdates(array $rows, LoggerInterface $logger, $dryRun)
    {
        foreach ($rows as $row) {
            $sql = 'UPDATE oro_security_permission SET group_names = :group_names WHERE id = :id';
            $params = ['group_names' => $row['group_names'], 'id' => $row['id']];
            $types = ['group_names' => Types::ARRAY, 'id' => Types::INTEGER];
            $this->logQuery($logger, $sql, $params, $types);

            if (!$dryRun) {
                $this->connection->executeUpdate($sql, $params, $types);
            }
        }
    }
}
