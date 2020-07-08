<?php

namespace Oro\Bundle\SegmentBundle\Tests\Functional\Entity\Repository;

use Oro\Bundle\SegmentBundle\Entity\Repository\SegmentRepository;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Tests\Functional\DataFixtures\LoadSegmentData;
use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class SegmentRepositoryTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures([LoadSegmentData::class]);
    }

    public function testFindByEntity(): void
    {
        $container = $this->getContainer();

        /** @var SegmentRepository $segmentRepository */
        $segmentRepository = $container->get('doctrine')->getRepository(Segment::class);

        $result = $segmentRepository->findByEntity(
            $container->get('oro_security.acl_helper'),
            WorkflowAwareEntity::class
        );

        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        /** @var Segment $dynamicSegment */
        $dynamicSegmentWithFilter = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC_WITH_FILTER);
        /** @var Segment $staticSegment */
        $staticSegment = $this->getReference(LoadSegmentData::SEGMENT_STATIC);
        /** @var Segment $staticSegmentWithFilter */
        $staticSegmentWithFilter = $this->getReference(LoadSegmentData::SEGMENT_STATIC_WITH_FILTER_AND_SORTING);
        /** @var Segment $staticSegmentWithSegmentFilter */
        $staticSegmentWithSegmentFilter = $this->getReference(LoadSegmentData::SEGMENT_STATIC_WITH_SEGMENT_FILTER);

        $this->assertEquals([
            $dynamicSegment->getName() => $dynamicSegment->getId(),
            $dynamicSegmentWithFilter->getName() => $dynamicSegmentWithFilter->getId(),
            $staticSegment->getName() => $staticSegment->getId(),
            $staticSegmentWithFilter->getName() => $staticSegmentWithFilter->getId(),
            $staticSegmentWithSegmentFilter->getName() => $staticSegmentWithSegmentFilter->getId()
        ], $result);
    }
}
