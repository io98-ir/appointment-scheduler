<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use Vaqtyar\Modules\Notifications\Application\SmsSecrets;

final class FakeSecrets implements SmsSecrets
{
    /**
     * @param array<string, string> $values
     * @param list<string> $fixed names defined in wp-config.php.
     */
    public function __construct(public array $values, private readonly array $fixed = [])
    {
    }

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function set(string $name, string $value): void
    {
        if ('' === $value) {
            unset($this->values[$name]);
        } else {
            $this->values[$name] = $value;
        }
    }

    public function isFixed(string $name): bool
    {
        return \in_array($name, $this->fixed, true);
    }
}
