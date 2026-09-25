<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Settings;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Shared\Calendar;
use Vaqtyar\Shared\Digits;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\LargeSettings;

/**
 * Settings groups in the real wp_options table.
 */
final class SettingsTest extends \WP_UnitTestCase
{
    public function testAGroupIsSavedAndReadBack(): void
    {
        $settings = new Settings();

        $settings->save(new GeneralSettings(Calendar::Gregorian, Digits::Latin));
        // Through the database, not the cache.
        \wp_cache_flush();

        $general = $settings->get(GeneralSettings::class);
        self::assertSame(Calendar::Gregorian, $general->calendar);
        self::assertSame(Digits::Latin, $general->digits);
    }

    public function testOnlyGroupsReadOnEveryRequestAreAutoloaded(): void
    {
        $settings = new Settings();

        $settings->save(new GeneralSettings());
        $settings->save(new LargeSettings(['a']));

        // WordPress 6.6 stores on/off; older rows may still say yes/no.
        self::assertContains($this->autoload('settings_general'), ['on', 'yes']);
        self::assertContains($this->autoload('settings_large'), ['off', 'no']);
    }

    public function testAHandEditedOptionFallsBackToTheDefaults(): void
    {
        \update_option(Options::key('settings_general'), 'not an array');

        $general = (new Settings())->get(GeneralSettings::class);

        self::assertSame(Calendar::Jalali, $general->calendar);
    }

    private function autoload(string $name): ?string
    {
        $wpdb = $GLOBALS['wpdb'];
        self::assertInstanceOf(\wpdb::class, $wpdb);

        return (new Db($wpdb))->getVar(
            'SELECT autoload FROM %i WHERE option_name = %s',
            $wpdb->options,
            Options::key($name)
        );
    }
}
