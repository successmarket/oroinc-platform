<?php

namespace Oro\Bundle\UserBundle\Migrations\Schema\v1_24;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Oro\Bundle\UserBundle\Entity\UserManager;
use Psr\Log\LoggerInterface;

class InsertAuthStatusesQuery extends ParametrizedMigrationQuery
{
    /** @var $extendExtension */
    protected $extendExtension;

    /**
     * @param ExtendExtension $extendExtension
     */
    public function __construct(ExtendExtension $extendExtension)
    {
        $this->extendExtension = $extendExtension;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $logger->info('Insert default user auth statuses.');
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
     * @param bool $dryRun
     */
    public function doExecute(LoggerInterface $logger, $dryRun = false)
    {
        $tableName = $this->extendExtension->getNameGenerator()->generateEnumTableName('auth_status');

        $sql = 'INSERT INTO %s (id, name, priority, is_default) VALUES (:id, :name, :priority, :is_default)';
        $sql = sprintf($sql, $tableName);

        $statuses = [
            [
                ':id' => UserManager::STATUS_ACTIVE,
                ':name' => 'Active',
                ':priority' => 1,
                ':is_default' => true,
            ],
            [
                ':id' => UserManager::STATUS_EXPIRED,
                ':name' => 'Password reset',
                ':priority' => 2,
                ':is_default' => false,
            ],
        ];

        $types = [
            'id' => Types::STRING,
            'name' => Types::STRING,
            'priority' => Types::INTEGER,
            'is_default' => Types::BOOLEAN,
        ];

        foreach ($statuses as $status) {
            $this->logQuery($logger, $sql, $status, $types);
            if (!$dryRun) {
                $this->connection->executeUpdate($sql, $status, $types);
            }
        }

        $defaultStatus = ['default_status' => UserManager::STATUS_ACTIVE];
        $defaultStatusType = ['default_status' => Types::STRING];

        $sql = 'UPDATE oro_user SET auth_status_id = :default_status';

        $this->logQuery($logger, $sql, $defaultStatus, $types);

        if (!$dryRun) {
            $this->connection->executeUpdate($sql, $defaultStatus, $defaultStatusType);
        }
    }
}
