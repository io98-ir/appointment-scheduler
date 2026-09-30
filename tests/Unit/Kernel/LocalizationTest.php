<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Localization;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Language;

/**
 * Which language the plugin's own strings and scripts are loaded for.
 */
final class LocalizationTest extends TestCase
{
    private string $siteLocale = 'en_US';
    private Language $language = Language::Auto;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('determine_locale')->alias(fn (): string => $this->siteLocale);
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => Options::key('settings_general') === $name
                ? (new GeneralSettings(Calendar::Jalali, Digits::Persian, $this->language))->toStored()
                : $default
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function localization(): Localization
    {
        return new Localization('/wp-content/plugins/vaqtyar/vaqtyar.php', new Settings());
    }

    public function testThePluginLocaleIsTheOwnersChoiceForThisPluginOnly(): void
    {
        $this->language = Language::Persian;

        self::assertSame('fa_IR', $this->localization()->pluginLocale('en_US', 'vaqtyar'));
        self::assertSame('en_US', $this->localization()->pluginLocale('en_US', 'another-plugin'));
    }

    public function testAnAutoLanguageFollowsTheSite(): void
    {
        $this->siteLocale = 'fa_IR';

        self::assertSame('fa_IR', $this->localization()->locale());
        self::assertSame('rtl', $this->localization()->direction());
    }

    public function testAScriptFileIsPointedAtThePluginsLanguage(): void
    {
        $this->siteLocale = 'en_US';
        $this->language = Language::Persian;
        $file = '/p/languages/vaqtyar-en_US-vaqtyar-admin.json';

        self::assertSame(
            '/p/languages/vaqtyar-fa_IR-vaqtyar-admin.json',
            $this->localization()->scriptFile($file, 'vaqtyar-admin', 'vaqtyar')
        );
    }

    public function testEnglishOnAPersianSiteFindsNoScriptFileSoItStaysEnglish(): void
    {
        $this->siteLocale = 'fa_IR';
        $this->language = Language::English;

        self::assertSame(
            '/p/languages/vaqtyar-en_US-vaqtyar-admin.json',
            $this->localization()->scriptFile(
                '/p/languages/vaqtyar-fa_IR-vaqtyar-admin.json',
                'vaqtyar-admin',
                'vaqtyar'
            )
        );
    }

    public function testAnotherDomainAndAMatchingLocaleAreLeftAlone(): void
    {
        $this->siteLocale = 'fa_IR';
        $this->language = Language::Auto;
        $file = '/p/languages/vaqtyar-fa_IR-vaqtyar-admin.json';

        self::assertSame($file, $this->localization()->scriptFile($file, 'h', 'vaqtyar'));
        self::assertSame($file, $this->localization()->scriptFile($file, 'h', 'other'));
        self::assertFalse($this->localization()->scriptFile(false, 'h', 'vaqtyar'));
    }
}
