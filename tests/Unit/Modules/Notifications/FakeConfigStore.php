<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Application\SmsConfigStore;

final class FakeConfigStore implements SmsConfigStore
{
    public function __construct(public SmsConfig $config)
    {
    }

    public function get(): SmsConfig
    {
        return $this->config;
    }

    public function save(SmsConfig $config): void
    {
        $this->config = $config;
    }
}
