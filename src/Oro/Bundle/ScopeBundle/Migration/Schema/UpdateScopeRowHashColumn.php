<?php

namespace Oro\Bundle\ScopeBundle\Migration\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\ScopeBundle\Migration\AddCommentToRoHashManager;
use Oro\Bundle\ScopeBundle\Migration\Query\AddScopeUniquenessQuery;
use Oro\Bundle\ScopeBundle\Migration\Query\AddTriggerToRowHashQuery;

/**
 * Updated row_hash and comment
 */
class UpdateScopeRowHashColumn implements Migration
{
    /**
     * @var AddCommentToRoHashManager
     */
    protected $manager;

    /**
     * @param AddCommentToRoHashManager $manager
     */
    public function __construct(AddCommentToRoHashManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @param Schema $schema
     * @param QueryBag $queries
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_scope');
        if (!$table->hasColumn('row_hash')) {
            return;
        }

        $comment = $table->getColumn('row_hash')->getComment();
        $relations = $this->manager->getRelations();

        // For cases when was added new relation or removed old
        if ($comment !== $relations) {
            $queries->addQuery(new AddScopeUniquenessQuery());
            $queries->addQuery(new AddTriggerToRowHashQuery());
            $this->manager->addRowHashComment($schema);
        }
    }
}
