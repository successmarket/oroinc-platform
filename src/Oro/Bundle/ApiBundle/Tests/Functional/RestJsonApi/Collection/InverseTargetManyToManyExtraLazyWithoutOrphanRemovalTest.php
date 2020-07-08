<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApi\Collection;

use Doctrine\Common\Collections\Collection;

/**
 * @group regression
 * @dbIsolationPerTest
 */
class InverseTargetManyToManyExtraLazyWithoutOrphanRemovalTest extends AbstractTargetManyToManyCollectionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures([
            '@OroApiBundle/Tests/Functional/DataFixtures/collection/timtm_extra_lazy_without_orphan_removal.yml'
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
        return 'inverseManyToManyExtraLazyWithoutOrphanRemovalParents';
    }

    /**
     * {@inheritDoc}
     */
    protected function getItems($entity): Collection
    {
        return $entity->getInverseManyToManyExtraLazyWithoutOrphanRemovalParents();
    }

    public function testTryToUpdateWithRemoveItemFromCollectionAndHasValidationErrors()
    {
        $this->markTestSkipped('Remove this method in BAP-19905');
    }

    public function testTryToUpdateWithRemoveAllItemsFromCollectionAndHasValidationErrors()
    {
        $this->markTestSkipped('Remove this method in BAP-19905');
    }
}
