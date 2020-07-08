<?php

namespace Oro\Bundle\EmailBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Oro\Bundle\MigrationBundle\Migration\ArrayLogger;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedMigrationQuery;
use Psr\Log\LoggerInterface;

class UpdateDateActivityListQuery extends ParametrizedMigrationQuery
{
    /**
     * {inheritdoc}
     */
    public function getDescription()
    {
        $logger = new ArrayLogger();
        $this->migrateActivityDates($logger, true);

        return $logger->getMessages();
    }

    /**
     * {inheritdoc}
     */
    public function execute(LoggerInterface $logger)
    {
        $this->migrateActivityDates($logger);
    }

    /**
     * @param LoggerInterface $logger
     * @param bool            $dryRun
     */
    protected function migrateActivityDates(LoggerInterface $logger, $dryRun = false)
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSqlPlatform) {
            $query  = <<<DQL
UPDATE oro_activity_list
SET created_at = e.sent, updated_at = e.sent
FROM
    oro_activity_list al
    LEFT JOIN oro_email e ON e.id = al.related_activity_id
        AND al.related_activity_class = 'Oro\\\\Bundle\\\\EmailBundle\\\\Entity\\\\Email'
WHERE al.related_activity_class = 'Oro\\\\Bundle\\\\EmailBundle\\\\Entity\\\\Email';
DQL;
        } else {
            $query  = <<<DQL
UPDATE
    oro_activity_list al
    LEFT JOIN oro_email e ON e.id = al.related_activity_id
        AND al.related_activity_class = 'Oro\\\\Bundle\\\\EmailBundle\\\\Entity\\\\Email'
SET al.created_at = e.sent, al.updated_at = e.sent
WHERE al.related_activity_class = 'Oro\\\\Bundle\\\\EmailBundle\\\\Entity\\\\Email'
DQL;
        }

        $this->logQuery($logger, $query);
        if (!$dryRun) {
            $this->connection->executeUpdate($query);
        }
    }
}
