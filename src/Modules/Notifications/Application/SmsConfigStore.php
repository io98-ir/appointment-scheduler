<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * Where the SMS configuration is kept.
 */
interface SmsConfigStore
{
    public function get(): SmsConfig;

    public function save(SmsConfig $config): void;
}
