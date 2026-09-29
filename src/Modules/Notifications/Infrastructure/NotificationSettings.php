<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;

/**
 * Where admin messages go and when reminders are held back. Quiet hours are
 * minutes after midnight, by default 22:00 to 08:00. An empty admin email
 * means the site's own administration address (WordPress' admin_email). The
 * screen for it comes with onboarding (T6.1).
 */
final class NotificationSettings implements SettingsGroup
{
    public const DEFAULT_QUIET_FROM = 22 * 60;
    public const DEFAULT_QUIET_TO = 8 * 60;

    public function __construct(
        public readonly string $adminEmail = '',
        public readonly int $quietFromMin = self::DEFAULT_QUIET_FROM,
        public readonly int $quietToMin = self::DEFAULT_QUIET_TO,
    ) {
    }

    public static function name(): string
    {
        return 'notifications';
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
        $email = $stored['admin_email'] ?? null;
        $from = $stored['quiet_from'] ?? null;
        $to = $stored['quiet_to'] ?? null;

        return new self(
            \is_string($email) ? $email : '',
            self::minute($from, self::DEFAULT_QUIET_FROM),
            self::minute($to, self::DEFAULT_QUIET_TO)
        );
    }

    /**
     * @return array{admin_email: string, quiet_from: int, quiet_to: int}
     */
    public function toStored(): array
    {
        return [
            'admin_email' => $this->adminEmail,
            'quiet_from' => $this->quietFromMin,
            'quiet_to' => $this->quietToMin,
        ];
    }

    private static function minute(mixed $value, int $default): int
    {
        return \is_int($value) && $value >= 0 && $value < 1440 ? $value : $default;
    }
}
