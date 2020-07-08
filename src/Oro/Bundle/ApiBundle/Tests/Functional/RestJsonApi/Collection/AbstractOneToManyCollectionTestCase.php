<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApi\Collection;

use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity\TestCollection;
use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity\TestCollectionItem;

abstract class AbstractOneToManyCollectionTestCase extends AbstractCollectionTestCase
{
    /**
     * @return string
     */
    protected function getCollectionEntityClass(): string
    {
        return TestCollection::class;
    }

    /**
     * @return string
     */
    protected function getCollectionItemEntityClass(): string
    {
        return TestCollectionItem::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function isManyToMany(): bool
    {
        return false;
    }
}
