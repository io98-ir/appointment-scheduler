<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Application\SmsConfigStore;

/**
 * SmsConfigStore on the Kernel's Settings.
 */
final class SettingsSmsConfigStore implements SmsConfigStore
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function get(): SmsConfig
    {
        return $this->settings->get(SmsSettings::class)->config;
    }

    public function save(SmsConfig $config): void
    {
        $this->settings->save(new SmsSettings($config));
    }
}
