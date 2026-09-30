<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

use Vaqtyar\Kernel\Settings\GeneralSettings;
use Vaqtyar\Kernel\Settings\Settings;

/**
 * Loads the plugin's own translations for the language the owner chose
 * (Language), which need not be WordPress's. Both the PHP strings (.mo) and
 * the scripts' (JED json) follow it.
 *
 * WordPress's just-in-time loading only reads translations that live in
 * WP_LANG_DIR, so the bundled ones are loaded here, on init: before that
 * WP 6.7+ complains about a translation call (implementation-notes §3).
 */
final class Localization
{
    private const DOMAIN = 'vaqtyar';

    public function __construct(private readonly string $pluginFile, private readonly Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('init', [$this, 'load'], 1);
        \add_filter('plugin_locale', [$this, 'pluginLocale'], 10, 2);
        \add_filter('load_script_translation_file', [$this, 'scriptFile'], 10, 3);
    }

    /**
     * On init.
     */
    public function load(): void
    {
        \load_plugin_textdomain(self::DOMAIN, false, \dirname(\plugin_basename($this->pluginFile)) . '/languages');
    }

    /**
     * The locale load_plugin_textdomain() reads the .mo for.
     */
    public function pluginLocale(string $locale, string $domain): string
    {
        return self::DOMAIN === $domain ? $this->locale($locale) : $locale;
    }

    /**
     * WordPress names a script's translation file after its own locale;
     * this points it at the plugin's language instead. A language without a
     * file (English) finds nothing and the script keeps its English strings.
     *
     */
    public function scriptFile(string|false $file, string $handle, string $domain): string|false
    {
        if (self::DOMAIN !== $domain || false === $file) {
            return $file;
        }
        $site = \determine_locale();
        $wanted = $this->locale($site);
        if ($wanted === $site) {
            return $file;
        }
        $name = \basename($file);
        $prefix = self::DOMAIN . '-' . $site . '-';
        if (!\str_starts_with($name, $prefix)) {
            return $file;
        }

        return \dirname($file) . '/' . self::DOMAIN . '-' . $wanted . '-' . \substr($name, \strlen($prefix));
    }

    /**
     * The locale the plugin speaks: the owner's choice, or the site's own.
     *
     * @param string|null $siteLocale WordPress's; read when omitted.
     */
    public function locale(?string $siteLocale = null): string
    {
        return $this->settings->get(GeneralSettings::class)->language->locale($siteLocale ?? \determine_locale());
    }

    /**
     * @param string|null $siteLocale WordPress's; read when omitted.
     * @return 'rtl'|'ltr'
     */
    public function direction(?string $siteLocale = null): string
    {
        return $this->settings->get(GeneralSettings::class)->language->direction($siteLocale ?? \determine_locale());
    }
}
