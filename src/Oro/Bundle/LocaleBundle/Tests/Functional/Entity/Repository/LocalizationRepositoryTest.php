<?php

namespace Oro\Bundle\LocaleBundle\Tests\Functional\Entity\Repository;

use Doctrine\ORM\EntityManager;
use Gedmo\Tool\Logging\DBAL\QueryAnalyzer;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Entity\Repository\LocalizationRepository;
use Oro\Bundle\LocaleBundle\Tests\Functional\DataFixtures\LoadLocalizationData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class LocalizationRepositoryTest extends WebTestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var LocalizationRepository
     */
    protected $repository;

    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadLocalizationData::class]);
        $this->em = $this->getContainer()->get('doctrine')->getManagerForClass('OroLocaleBundle:Localization');
        $this->repository = $this->em->getRepository('OroLocaleBundle:Localization');
    }

    public function testFindRootsWithChildren()
    {
        $localizations = [
            $this->getReference(LoadLocalizationData::DEFAULT_LOCALIZATION_CODE),
            $this->getReference('es')
        ];
        $queryAnalyzer = new QueryAnalyzer($this->em->getConnection()->getDatabasePlatform());

        $prevLogger = $this->em->getConnection()->getConfiguration()->getSQLLogger();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($queryAnalyzer);

        /** @var Localization[] $result */
        $result = $this->repository->findRootsWithChildren();

        $this->assertEquals(array_values($localizations), array_values($result));

        foreach ($result as $root) {
            $this->visitChildren($root);
        }

        $queries = $queryAnalyzer->getExecutedQueries();

        $this->assertCount(count($localizations) + 2, $queries);

        $this->em->getConnection()->getConfiguration()->setSQLLogger($prevLogger);
    }

    /**
     * @param Localization $localization
     */
    protected function visitChildren(Localization $localization)
    {
        $localization->getLanguageCode();
        foreach ($localization->getChildLocalizations() as $child) {
            $this->visitChildren($child);
        }
    }

    public function testGetLocalizationsCount()
    {
        $result = $this->repository->getLocalizationsCount();

        $this->assertInternalType('int', $result);
        $this->assertEquals(3, $result);
    }

    public function testGetBatchIterator()
    {
        $expectedLocalizations = [$this->getDefaultLocalization()->getTitle()];
        foreach (LoadLocalizationData::getLocalizations() as $localization) {
            $expectedLocalizations[] = $localization['title'];
        }

        $localizations = [];
        foreach ($this->repository->getBatchIterator() as $localization) {
            $localizations[] = $localization->getTitle();
        }

        $this->assertEquals($expectedLocalizations, $localizations);
    }

    public function testFindOneByLanguageCodeAndFormattingCode()
    {
        $this->assertTrue(null === $this->repository->findOneByLanguageCodeAndFormattingCode('mx', 'mx'));

        $localization = $this->repository->findOneByLanguageCodeAndFormattingCode('en_CA', 'en_CA');

        $this->assertFalse(null === $localization);
        $this->assertEquals('English (Canada)', $localization->getDefaultTitle());
    }

    public function testGetLocalizationsData()
    {
        $actual = $this->repository->getLocalizationsData();

        $expected = $this->repository->findAll();
        $this->assertCount(count($expected), $actual);

        /** @var Localization $localization */
        foreach ($this->repository->findAll() as $localization) {
            $this->assertArrayHasKey($localization->getId(), $actual);
            $this->assertEquals(
                [
                    'languageCode' => $localization->getLanguageCode(),
                    'formattingCode' => $localization->getFormattingCode(),
                ],
                $actual[$localization->getId()]
            );
        }
    }

    /**
     * @return object|Localization
     */
    protected function getDefaultLocalization()
    {
        $localeSettings = $this->getContainer()->get('oro_locale.settings');
        $locale = $localeSettings->getLocale();
        list($language) = explode('_', $locale);

        return $this->repository->findOneBy([
            'language' => $this->getReference('language.' . $language),
            'formattingCode' => $locale
        ]);
    }
}
