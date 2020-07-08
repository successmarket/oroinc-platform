<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Translation;

use Oro\Bundle\EntityConfigBundle\Translation\ConfigTranslationHelper;
use Oro\Bundle\TranslationBundle\Entity\Translation;
use Oro\Bundle\TranslationBundle\Manager\TranslationManager;
use Oro\Bundle\TranslationBundle\Translation\Translator;

class ConfigTranslationHelperTest extends \PHPUnit\Framework\TestCase
{
    const LOCALE = 'en';

    /** @var \PHPUnit\Framework\MockObject\MockObject|TranslationManager */
    protected $translationManager;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Translator */
    protected $translator;

    /** @var ConfigTranslationHelper */
    protected $helper;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(Translator::class);

        $this->translationManager = $this
            ->getMockBuilder(TranslationManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->helper = new ConfigTranslationHelper(
            $this->translationManager,
            $this->translator
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->translator,
            $this->helper,
            $this->translationManager
        );
    }

    /**
     * @dataProvider isTranslationEqualDataProvider
     *
     * @param string $translation
     * @param string $key
     * @param string $value
     * @param bool $expected
     */
    public function testIsTranslationEqual($translation, $key, $value, $expected)
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with($key)
            ->willReturn($translation);

        $this->assertEquals($expected, $this->helper->isTranslationEqual($key, $value));
    }

    /**
     * @return array
     */
    public function isTranslationEqualDataProvider()
    {
        return [
            'equal' => [
                'translation' => 'valid translation',
                'key' => 'test',
                'value' => 'valid translation',
                'expected' => true
            ],
            'not equal' => [
                'translation' => 'valid translation',
                'key' => 'test',
                'value' => 'invalid value',
                'expected' => false
            ]
        ];
    }

    public function testInvalidateCache()
    {
        $this->translationManager->expects($this->once())
            ->method('invalidateCache');

        $this->helper->invalidateCache();
    }

    public function testInvalidateCacheWithLocale()
    {
        $this->translationManager->expects($this->once())
            ->method('invalidateCache')
            ->with('test_locale');

        $this->helper->invalidateCache('test_locale');
    }

    /**
     * @dataProvider saveTranslationsDataProvider
     *
     * @param array $translations
     * @param string|null $key
     * @param string|null $value
     */
    public function testSaveTranslations(array $translations, $key = null, $value = null)
    {
        if ($translations) {
            $this->assertTranslationManagerCalled($key, $value);
            $this->assertTranslationServicesCalled();
        } else {
            $this->translationManager->expects($this->never())->method($this->anything());
        }

        $this->helper->saveTranslations($translations);
    }

    /**
     * @return array
     */
    public function saveTranslationsDataProvider()
    {
        $key = 'test.domain.label';
        $value = 'translation label';

        return [
            [
                'translations' => []
            ],
            [
                'translations' => [$key => $value],
                'key' => $key,
                'value' => $value
            ],
        ];
    }

    /**
     * @param string $key
     * @param string $value
     */
    protected function assertTranslationManagerCalled($key, $value)
    {
        $trans = new Translation();

        $this->translationManager->expects($this->once())
            ->method('saveTranslation')
            ->with($key, $value, self::LOCALE, TranslationManager::DEFAULT_DOMAIN)
            ->willReturn($trans);

        $this->translationManager->expects($this->once())
            ->method('invalidateCache')
            ->with(self::LOCALE);

        $this->translationManager->expects($this->once())
            ->method('flush');
    }

    protected function assertTranslationServicesCalled()
    {
        $this->translator->expects($this->once())
            ->method('getLocale')
            ->willReturn(self::LOCALE);
    }

    public function testTranslateWithFallbackTranslationExists()
    {
        $id = 'string';
        $translation = 'translation';
        $fallback = 'fallback';

        $this->translator->expects(self::once())
            ->method('hasTrans')
            ->with($id)
            ->willReturn(true);

        $this->translator->expects(self::once())
            ->method('trans')
            ->with($id)
            ->willReturn($translation);

        self::assertEquals($translation, $this->helper->translateWithFallback($id, $fallback));
    }

    public function testTranslateWithFallbackTranslationDoesntExist()
    {
        $id = 'string';
        $fallback = 'fallback';

        $this->translator->expects(self::once())
            ->method('hasTrans')
            ->with($id)
            ->willReturn(false);

        $this->translator->expects(self::never())
            ->method('trans');

        self::assertEquals($fallback, $this->helper->translateWithFallback($id, $fallback));
    }
}
