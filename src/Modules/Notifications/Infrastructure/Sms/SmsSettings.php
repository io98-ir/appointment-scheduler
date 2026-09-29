<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Kernel\Settings\SettingsGroup;
use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Domain\SmsCatalog;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * SmsConfig as a settings group. What no longer parses (a hand-edited
 * option) falls back to the defaults, so the site keeps sending.
 */
final class SmsSettings implements SettingsGroup
{
    public function __construct(public readonly SmsConfig $config = new SmsConfig())
    {
    }

    public static function name(): string
    {
        return 'sms';
    }

    public static function autoload(): bool
    {
        return false;
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static
    {
        try {
            return new self(new SmsConfig(
                self::strings($stored['order'] ?? null, SmsCatalog::IDS),
                self::map($stored['senders'] ?? null),
                self::map($stored['otp_patterns'] ?? null)
            ));
        } catch (InvalidValue) {
            return new self();
        }
    }

    /**
     * @return array{order: list<string>, senders: array<string, string>, otp_patterns: array<string, string>}
     */
    public function toStored(): array
    {
        return [
            'order' => $this->config->order,
            'senders' => $this->config->senders,
            'otp_patterns' => $this->config->otpPatterns,
        ];
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    private static function strings(mixed $value, array $default): array
    {
        if (!\is_array($value)) {
            return $default;
        }

        return \array_values(\array_filter($value, \is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private static function map(mixed $value): array
    {
        $map = [];
        foreach (\is_array($value) ? $value : [] as $key => $item) {
            if (\is_string($key) && \is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }
}
