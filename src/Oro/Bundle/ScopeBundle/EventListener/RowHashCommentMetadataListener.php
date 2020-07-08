<?php

namespace Oro\Bundle\ScopeBundle\EventListener;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Oro\Bundle\ScopeBundle\Migration\AddCommentToRoHashManager;

/**
 * Added comment metadata to row_hash column
 */
class RowHashCommentMetadataListener
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
     * @param LoadClassMetadataEventArgs $event
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $em = $event->getEntityManager();
        if (!$this->isPlatformSupport($em)) {
            return;
        }

        /** @var ClassMetadata $metadata */
        $metadata = $event->getClassMetadata();
        if ($metadata->getTableName() !== 'oro_scope') {
            return;
        }

        $relations = $this->manager->getRelations();
        $metadata->setAttributeOverride(
            'rowHash',
            array_merge(
                $metadata->fieldMappings['rowHash'],
                ['options' => ['comment' => $relations]]
            )
        );
    }

    /**
     * @param EntityManagerInterface $em
     * @return bool
     */
    protected function isPlatformSupport(EntityManagerInterface $em): bool
    {
        $platform = $em->getConnection()->getDatabasePlatform();
        return ($platform instanceof PostgreSqlPlatform || $platform instanceof MySqlPlatform);
    }
}
