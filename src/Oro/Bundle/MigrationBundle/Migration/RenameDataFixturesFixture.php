<?php

namespace Oro\Bundle\MigrationBundle\Migration;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\MigrationBundle\Entity\DataFixture;

/**
 * Change class names for renamed data fixtures
 */
class RenameDataFixturesFixture extends AbstractFixture
{
    /** @var string[] */
    private $renamedDataFixtures = [];

    /**
     * @param string $previousClassName
     * @param string $currentClassName
     */
    public function addRename(string $previousClassName, string $currentClassName): void
    {
        $this->renamedDataFixtures[$previousClassName] = $currentClassName;
    }

    /**
     * @return bool
     */
    public function isNeedPerform(): bool
    {
        return (bool)$this->renamedDataFixtures;
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        /** @var DataFixture[] $dataFixtures */
        $dataFixtures = $manager->getRepository('OroMigrationBundle:DataFixture')
            ->findBy(['className' => \array_keys($this->renamedDataFixtures)]);

        foreach ($dataFixtures as $dataFixture) {
            $dataFixture->setClassName($this->renamedDataFixtures[$dataFixture->getClassName()]);
        }

        $manager->flush();
    }
}
