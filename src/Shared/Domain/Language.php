<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The language the plugin's own screens speak: Persian, English, or whatever
 * the site speaks. It is the plugin's choice, not WordPress's, so an English
 * wp-admin can still run a Persian booking screen.
 */
enum Language: string
{
    case Auto = 'auto';
    case Persian = 'fa';
    case English = 'en';

    public const PERSIAN_LOCALE = 'fa_IR';
    public const ENGLISH_LOCALE = 'en_US';

    /**
     * The locale the plugin's translations are loaded for.
     *
     * @param string $siteLocale WordPress's own, e.g. "fa_IR" or "de_DE"; only used for Auto.
     */
    public function locale(string $siteLocale): string
    {
        return match ($this) {
            self::Persian => self::PERSIAN_LOCALE,
            self::English => self::ENGLISH_LOCALE,
            // No other locale has a translation, so it reads English.
            self::Auto => \str_starts_with(\strtolower($siteLocale), 'fa')
                ? self::PERSIAN_LOCALE
                : self::ENGLISH_LOCALE,
        };
    }

    /**
     * @param string $siteLocale See locale().
     * @return 'rtl'|'ltr'
     */
    public function direction(string $siteLocale): string
    {
        return self::PERSIAN_LOCALE === $this->locale($siteLocale) ? 'rtl' : 'ltr';
    }
}
