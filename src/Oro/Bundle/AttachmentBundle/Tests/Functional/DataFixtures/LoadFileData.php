<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\AbstractFixture;
use Oro\Bundle\UserBundle\DataFixtures\UserUtilityTrait;

class LoadFileData extends AbstractFixture
{
    use UserUtilityTrait;
    public const FILE_1 = 'file_1';
    public const FILE_2 = 'file_2';
    public const FILE_3 = 'file_3';

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager): void
    {
        $file = new File();
        $file->setFilename('file_a');
        $file->setParentEntityClass(\stdClass::class);
        $file->setParentEntityId(1);
        $file->setParentEntityFieldName('fieldA');
        $manager->persist($file);
        $this->setReference(self::FILE_1, $file);

        $file = new File();
        $file->setFilename('file_b');
        $file->setParentEntityClass(\stdClass::class);
        $file->setParentEntityId(2);
        $file->setParentEntityFieldName('fieldB');
        $manager->persist($file);
        $this->setReference(self::FILE_2, $file);

        $file = new File();
        $file->setFilename('file_c');
        $file->setParentEntityClass(\stdClass::class);
        $file->setParentEntityId(1);
        $file->setParentEntityFieldName('fieldC');
        $manager->persist($file);
        $this->setReference(self::FILE_3, $file);

        $manager->flush();
    }
}
