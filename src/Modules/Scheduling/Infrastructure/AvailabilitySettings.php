<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;
use Vaqtyar\Modules\Scheduling\Application\AvailabilityDefaults;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;

/**
 * The site-wide availability settings (AvailabilityDefaults). Read by the
 * availability API only, so not autoloaded. The settings UI comes with
 * onboarding (T6.1); until then the defaults apply.
 */
final class AvailabilitySettings implements SettingsGroup
{
    private const MAX_NOTICE_MIN = 365 * 1440;
    private const MAX_ADVANCE_DAYS = 730;

    /**
     * @param int $slotStepMin 1 to 1440.
     * @param int $minNoticeMin 0 to a year.
     * @param int $maxAdvanceDays 1 to two years.
     */
    public function __construct(
        public readonly int $slotStepMin = 30,
        public readonly int $minNoticeMin = 60,
        public readonly int $maxAdvanceDays = 60,
        public readonly StaffChoice $staffChoice = StaffChoice::LeastBusy,
    ) {
    }

    public static function name(): string
    {
        return 'availability';
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
        $choice = $stored['staff_choice'] ?? null;
        $defaults = new self();

        return new self(
            self::within($stored['slot_step_min'] ?? null, 1, 1440) ?? $defaults->slotStepMin,
            self::within($stored['min_notice_min'] ?? null, 0, self::MAX_NOTICE_MIN) ?? $defaults->minNoticeMin,
            self::within($stored['max_advance_days'] ?? null, 1, self::MAX_ADVANCE_DAYS) ?? $defaults->maxAdvanceDays,
            (\is_string($choice) ? StaffChoice::tryFrom($choice) : null) ?? $defaults->staffChoice,
        );
    }

    /**
     * @return array{slot_step_min: int, min_notice_min: int, max_advance_days: int, staff_choice: string}
     */
    public function toStored(): array
    {
        return [
            'slot_step_min' => $this->slotStepMin,
            'min_notice_min' => $this->minNoticeMin,
            'max_advance_days' => $this->maxAdvanceDays,
            'staff_choice' => $this->staffChoice->value,
        ];
    }

    public function defaults(): AvailabilityDefaults
    {
        return new AvailabilityDefaults(
            $this->slotStepMin,
            $this->minNoticeMin,
            $this->maxAdvanceDays * 1440,
            $this->staffChoice
        );
    }

    private static function within(mixed $value, int $min, int $max): ?int
    {
        return \is_int($value) && $value >= $min && $value <= $max ? $value : null;
    }
}
