<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Shared\Calendar;
use Vaqtyar\Shared\Digits;
use Vaqtyar\Tests\Unit\Kernel\Fixtures\LargeSettings;

final class SettingsTest extends TestCase
{
    /** @var array<string, array{mixed, bool|null}> Option => value and autoload. */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => isset($this->options[$name])
                ? $this->options[$name][0]
                : $default
        );
        Functions\when('update_option')->alias(function (string $name, mixed $value, ?bool $autoload = null): bool {
            $this->options[$name] = [$value, $autoload];

            return true;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testDefaultsWhenNothingIsStored(): void
    {
        $general = (new Settings())->get(GeneralSettings::class);

        self::assertSame(Calendar::Jalali, $general->calendar);
        self::assertSame(Digits::Persian, $general->digits);
    }

    public function testSavesAGroupAsOneOptionAndReadsItBack(): void
    {
        $settings = new Settings();

        $settings->save(new GeneralSettings(Calendar::Gregorian, Digits::Latin));

        self::assertSame(
            [['calendar' => 'gregorian', 'digits' => 'latin'], true],
            $this->options[Options::key('settings_general')]
        );
        $general = $settings->get(GeneralSettings::class);
        self::assertSame(Calendar::Gregorian, $general->calendar);
        self::assertSame(Digits::Latin, $general->digits);
    }

    public function testALargeGroupIsNotAutoloaded(): void
    {
        (new Settings())->save(new LargeSettings(['a', 'b']));

        self::assertSame([['items' => ['a', 'b']], false], $this->options[Options::key('settings_large')]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function brokenOptions(): iterable
    {
        yield 'not an array' => ['gregorian'];
        yield 'unknown values' => [['calendar' => 'lunar', 'digits' => 'roman']];
        yield 'wrong types' => [['calendar' => 1, 'digits' => ['latin']]];
    }

    /**
     * @dataProvider brokenOptions
     */
    public function testABrokenOptionFallsBackToTheDefaults(mixed $stored): void
    {
        $this->options[Options::key('settings_general')] = [$stored, true];

        $general = (new Settings())->get(GeneralSettings::class);

        self::assertSame(Calendar::Jalali, $general->calendar);
        self::assertSame(Digits::Persian, $general->digits);
    }

    public function testEachValueFallsBackOnItsOwn(): void
    {
        $this->options[Options::key('settings_general')] = [['calendar' => 'gregorian', 'digits' => 'roman'], true];

        $general = (new Settings())->get(GeneralSettings::class);

        self::assertSame(Calendar::Gregorian, $general->calendar);
        self::assertSame(Digits::Persian, $general->digits);
    }
}
