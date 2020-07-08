<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApi\Collection;

use Doctrine\Common\Collections\Collection;

/**
 * @group regression
 * @dbIsolationPerTest
 */
class InverseTargetManyToManyWithoutOrphanRemovalTest extends AbstractTargetManyToManyCollectionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures([
            '@OroApiBundle/Tests/Functional/DataFixtures/collection/timtm_without_orphan_removal.yml'
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function isOrphanRemoval(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function getAssociationName(): string
    {
        return 'inverseManyToManyWithoutOrphanRemovalParents';
    }

    /**
     * {@inheritDoc}
     */
    protected function getItems($entity): Collection
    {
        return $entity->getInverseManyToManyWithoutOrphanRemovalParents();
    }
}
