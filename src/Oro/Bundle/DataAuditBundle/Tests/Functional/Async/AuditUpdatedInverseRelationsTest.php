<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Functional\Async;

use Oro\Bundle\DataAuditBundle\Async\AuditChangedEntitiesInverseRelationsProcessor;
use Oro\Bundle\DataAuditBundle\Entity\AuditField;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataChild;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataOwner;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Transport\Null\NullSession;

/**
 * @dbIsolationPerTest
 */
class AuditUpdatedInverseRelationsTest extends WebTestCase
{
    use AuditChangedEntitiesExtensionTrait;

    protected function setUp()
    {
        $this->initClient();
    }

    public function testShouldCreateAuditsForInsertedUpdatedAndDeletedEntities()
    {
        $message = $this->createDummyMessage([
            'entities_inserted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            null,
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 321,
                            ]
                        ]
                    ]
                ]
            ],
            'entities_updated' => [
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 125,
                    'change_set' => [
                        'child' => [
                            null,
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 323,
                            ]
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 124,
                    'change_set' => [
                        'child' => [
                            null,
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 322,
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(6);
    }

    public function testShouldCreateAuditForAddedInverseSideEntityOnOneToOneRelation()
    {
        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            null,
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 321,
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(2);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(321, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals(null, $auditField->getOldValue());
        $this->assertEquals('Added: TestAuditDataOwner::123', $auditField->getNewValue());
    }

    public function testShouldCreateAuditForDeletedInverseSideEntityOnOneToOneRelation()
    {
        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 321,
                            ],
                            null
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(2);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(321, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals('Removed: TestAuditDataOwner::123', $auditField->getOldValue());
        $this->assertEquals(null, $auditField->getNewValue());
    }

    public function testShouldCreateAuditForReplacedInverseSideEntityOnOneToOneRelation()
    {
        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 155,
                            ],
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 166,
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(3);

        $audits = $this->findStoredAudits();

        $audit = $audits[0];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(166, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals(null, $auditField->getOldValue());
        $this->assertEquals('Added: TestAuditDataOwner::123', $auditField->getNewValue());

        $audit = $audits[1];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(155, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals('Removed: TestAuditDataOwner::123', $auditField->getOldValue());
        $this->assertEquals(null, $auditField->getNewValue());
    }

    public function testShouldCreateAuditForSwappedEntitiesOnOneToOneRelation()
    {
        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 155,
                            ],
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 166,
                            ]
                        ]
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 124,
                    'change_set' => [
                        'child' => [
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 166,
                            ],
                            [
                                'entity_class' => TestAuditDataChild::class,
                                'entity_id' => 155,
                            ]
                        ]
                    ]
                ],
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(4);

        $audits = $this->findStoredAudits();

        $audit = $audits[0];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(166, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals('Removed: TestAuditDataOwner::124', $auditField->getOldValue());
        $this->assertEquals('Added: TestAuditDataOwner::123', $auditField->getNewValue());

        $audit = $audits[1];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owner');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(155, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owner', $auditField->getField());
        $this->assertEquals('Removed: TestAuditDataOwner::123', $auditField->getOldValue());
        $this->assertEquals('Added: TestAuditDataOwner::124', $auditField->getNewValue());
    }

    public function testShouldCreateAuditForAddedInverseSideEntityOnManyToOneRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'ownerManyToOne' => [
                            null,
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 124,
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('childrenOneToMany');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataOwner::class, $audit->getObjectClass());
        $this->assertEquals(124, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('childrenOneToMany', $auditField->getField());
        $this->assertEquals("Added: TestAuditDataChild::123", $auditField->getNewValue());
        $this->assertEquals(null, $auditField->getOldValue());
    }

    public function testShouldCreateAuditForDeletedInverseSideEntityOnManyToOneRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'ownerManyToOne' => [
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 124,
                            ],
                            null
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('childrenOneToMany');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataOwner::class, $audit->getObjectClass());
        $this->assertEquals(124, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('childrenOneToMany', $auditField->getField());
        $this->assertEquals("Removed: TestAuditDataChild::123", $auditField->getOldValue());
        $this->assertEquals(null, $auditField->getNewValue());
    }

    public function testShouldCreateAuditForSwappedEntitiesOnManyToOneRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'ownerManyToOne' => [
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 130,
                            ],
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 131,
                            ]
                        ]
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 124,
                    'change_set' => [
                        'ownerManyToOne' => [
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 131,
                            ],
                            [
                                'entity_class' => TestAuditDataOwner::class,
                                'entity_id' => 130,
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(2);

        $audits = $this->findStoredAudits();

        $audit = $audits[0];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('childrenOneToMany');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataOwner::class, $audit->getObjectClass());
        $this->assertEquals(130, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('childrenOneToMany', $auditField->getField());
        $this->assertEquals("Removed: TestAuditDataChild::123", $auditField->getOldValue());
        $this->assertEquals("Added: TestAuditDataChild::124", $auditField->getNewValue());

        $audit = $audits[1];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('childrenOneToMany');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataOwner::class, $audit->getObjectClass());
        $this->assertEquals(131, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('childrenOneToMany', $auditField->getField());
        $this->assertEquals("Removed: TestAuditDataChild::124", $auditField->getOldValue());
        $this->assertEquals("Added: TestAuditDataChild::123", $auditField->getNewValue());
    }

    public function testShouldCreateAuditForAddedInverseSideEntityOnManyToManyRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [
                                'deleted' => [],
                            ],
                            [
                                'inserted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 124,
                                    ]
                                ],
                                'changed' => [],
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owners');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(124, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owners', $auditField->getField());
        $this->assertEquals('Added: TestAuditDataOwner::123', $auditField->getNewValue());
        $this->assertEquals(null, $auditField->getOldValue());
    }

    public function testShouldCreateAuditForDeletedInverseSideEntityOnManyToManyRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [
                                'deleted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 124,
                                    ]
                                ],
                            ],
                            [
                                'inserted' => [],
                                'changed' => [],
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owners');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(124, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owners', $auditField->getField());
        $this->assertEquals("Removed: TestAuditDataOwner::123", $auditField->getOldValue());
        $this->assertEquals(null, $auditField->getNewValue());
    }

    public function testShouldCreateAuditForSwappedEntitiesOnManyToManyRelation()
    {
        $message = $this->createDummyMessage([
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [
                                'deleted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 130,
                                    ]
                                ],
                            ],
                            [
                                'inserted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 131,
                                    ]
                                ],
                                'changed' => [],
                            ]
                        ]
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 124,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [
                                'deleted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 131,
                                    ]
                                ],
                            ],
                            [
                                'inserted' => [
                                    [
                                        'entity_class' => TestAuditDataChild::class,
                                        'entity_id' => 130,
                                    ]
                                ],
                                'changed' => [],
                            ]
                        ]
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(2);

        $audits = $this->findStoredAudits();

        $audit = $audits[0];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owners');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(130, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owners', $auditField->getField());
        $this->assertEquals("Added: TestAuditDataOwner::124", $auditField->getNewValue());
        $this->assertEquals("Removed: TestAuditDataOwner::123", $auditField->getOldValue());

        $audit = $audits[1];
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('owners');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertEquals(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(131, $audit->getObjectId());
        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('owners', $auditField->getField());
        $this->assertEquals("Added: TestAuditDataOwner::123", $auditField->getNewValue());
        $this->assertEquals("Removed: TestAuditDataOwner::124", $auditField->getOldValue());
    }

    public function testShouldTrackChangedEntityIfPartOfCollection()
    {
        $owner = $this->createOwner();
        $child = $this->createChild();

        $child->setOwnerManyToOne($owner);

        $this->getEntityManager()->flush();

        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => $child->getId(),
                    'change_set' => [
                        'stringProperty' => [null, 'foo'],
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(2);

        $audit = $this->findLastStoredAudit();
        $this->assertSame(TestAuditDataOwner::class, $audit->getObjectClass());
        $this->assertEquals($owner->getId(), $audit->getObjectId());
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('childrenOneToMany');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('childrenOneToMany', $auditField->getField());
        $this->assertEquals(
            "\nChanged: Item #" . $child->getId(),
            $auditField->getNewValue()
        );
        $this->assertEquals(null, $auditField->getOldValue());
    }

    public function testShouldTrackChangedEntityWhichPartOfCollectionIfSourceEntityNoLongerExist()
    {
        $child = $this->createChild();

        //gurad
        $this->assertNull($child->getOwnerManyToOne());

        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => $child->getId(),
                    'change_set' => [
                        'stringProperty' => [null, 'foo'],
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertSame(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals($child->getId(), $audit->getObjectId());
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('stringProperty');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('stringProperty', $auditField->getField());
        $this->assertEquals(null, $auditField->getOldValue());
        $this->assertEquals('foo', $auditField->getNewValue());
    }

    public function testShouldTrackChangedEntityWhichIsNotPartOfCollection()
    {
        $message = $this->createDummyMessage([
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'foo'],
                    ]
                ]
            ],
        ]);

        /** @var AuditChangedEntitiesInverseRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_inverse_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(1);

        $audit = $this->findLastStoredAudit();
        $this->assertSame(TestAuditDataChild::class, $audit->getObjectClass());
        $this->assertEquals(123, $audit->getObjectId());
        $this->assertCount(1, $audit->getFields());

        $auditField = $audit->getField('stringProperty');
        $this->assertInstanceOf(AuditField::class, $auditField);

        $this->assertSame($audit, $auditField->getAudit());
        $this->assertSame('text', $auditField->getDataType());
        $this->assertSame('stringProperty', $auditField->getField());
        $this->assertEquals(null, $auditField->getOldValue());
        $this->assertEquals('foo', $auditField->getNewValue());
    }
}
