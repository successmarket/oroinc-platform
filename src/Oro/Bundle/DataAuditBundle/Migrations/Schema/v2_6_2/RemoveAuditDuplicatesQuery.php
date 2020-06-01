<?php

namespace Oro\Bundle\DataAuditBundle\Migrations\Schema\v2_6_2;

use Doctrine\DBAL\Connection;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

/**
 * Remove duplicates for Audit entity with merging AuditField entities query.
 */
class RemoveAuditDuplicatesQuery extends ParametrizedMigrationQuery
{
    /**
     * @return string|string[]
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $this->doExecute($logger, true);

        return $logger->getMessages();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function execute(LoggerInterface $logger)
    {
        $this->doExecute($logger);
    }

    /**
     * @param LoggerInterface $logger
     * @param bool $dryRun
     */
    private function doExecute(LoggerInterface $logger, $dryRun = false): void
    {
        $sql = 'SELECT object_id, entity_id, object_class, transaction_id FROM oro_audit '.
            'GROUP BY object_id, entity_id, object_class, transaction_id HAVING COUNT(*) > 1';
        $this->logQuery($logger, $sql);
        $duplicatedGroups = $this->connection->fetchAll($sql);
        if (!$duplicatedGroups) {
            return;
        }

        $idsToRemove = [];
        foreach ($duplicatedGroups as $group) {
            $sql = 'SELECT id FROM oro_audit '.
                'WHERE object_id = ? AND entity_id = ? AND object_class = ? AND transaction_id = ?';
            $this->logQuery($logger, $sql);
            $duplicatedPair = $this->connection->fetchAll(
                $sql,
                [$group['object_id'], $group['entity_id'], $group['object_class'], $group['transaction_id']]
            );

            if (count($duplicatedPair) < 2) {
                continue;
            }

            $rowToCollect = array_shift($duplicatedPair);
            foreach ($duplicatedPair as $row) {
                $sql = 'UPDATE oro_audit_field SET audit_id = ? WHERE audit_id = ?';
                $this->logQuery($logger, $sql);
                if (!$dryRun) {
                    $this->connection->executeUpdate($sql, [$rowToCollect['id'], $row['id']]);
                }
                $idsToRemove[] = $row['id'];
            }
        }

        $sql = 'DELETE FROM oro_audit WHERE id IN (?)';
        $this->logQuery($logger, $sql);
        if (!$dryRun) {
            $this->connection->executeQuery($sql, [$idsToRemove], [Connection::PARAM_INT_ARRAY]);
        }
    }
}
