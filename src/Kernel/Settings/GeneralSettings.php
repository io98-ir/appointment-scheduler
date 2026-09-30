<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Settings;

use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Language;

/**
 * How dates and numbers are shown everywhere (DateFormatter), and the
 * language of the plugin's screens. Read on most requests, so autoloaded.
 * Until it is first saved (onboarding, T6.1) the option does not exist and
 * each read is a query without an object cache.
 */
final class GeneralSettings implements SettingsGroup
{
    public function __construct(
        public readonly Calendar $calendar = Calendar::Jalali,
        public readonly Digits $digits = Digits::Persian,
        public readonly Language $language = Language::Auto,
    ) {
    }

    public static function name(): string
    {
        return 'general';
    }

    public static function autoload(): bool
    {
        return true;
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static
    {
        $calendar = $stored['calendar'] ?? null;
        $digits = $stored['digits'] ?? null;
        $language = $stored['language'] ?? null;

        return new self(
            (\is_string($calendar) ? Calendar::tryFrom($calendar) : null) ?? Calendar::Jalali,
            (\is_string($digits) ? Digits::tryFrom($digits) : null) ?? Digits::Persian,
            (\is_string($language) ? Language::tryFrom($language) : null) ?? Language::Auto,
        );
    }

    /**
     * @return array{calendar: string, digits: string, language: string}
     */
    public function toStored(): array
    {
        return [
            'calendar' => $this->calendar->value,
            'digits' => $this->digits->value,
            'language' => $this->language->value,
        ];
    }
}
