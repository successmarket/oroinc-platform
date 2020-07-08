<?php

namespace Oro\Bundle\UserBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Types\Types;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedSqlMigrationQuery;
use Oro\Bundle\UserBundle\Entity\User;
use Psr\Log\LoggerInterface;

class SetOwnerForEmailTemplatesQuery extends ParametrizedSqlMigrationQuery
{
    /**
     * {@inheritdoc}
     */
    protected function processQueries(LoggerInterface $logger, $dryRun = false)
    {
        $qb = $this->connection->createQueryBuilder()
            ->update('oro_email_template')
            ->set('user_owner_id', '(' . $this->getAdminUserQuery() . ')');

        $this->addSql($qb->getSQL(), ['role' => User::ROLE_ADMINISTRATOR], ['role' => Types::STRING]);

        parent::processQueries($logger, $dryRun);
    }

    /**
     * @return string
     */
    protected function getAdminUserQuery()
    {
        return $this->connection->createQueryBuilder()
            ->select('u.id')
            ->from('oro_user', 'u')
            ->innerJoin('u', 'oro_user_access_role', 'rel', 'rel.user_id = u.id')
            ->innerJoin('rel', 'oro_access_role', 'r', 'r.id = rel.role_id')
            ->where('r.role = :role')
            ->setMaxResults(1)
            ->getSQL();
    }
}
