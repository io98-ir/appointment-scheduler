<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidayRepository;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Slug;

/**
 * The admin's own edits to a holiday calendar. The shipped datasets
 * (assets/holidays) are written only by ImportHolidays, never through here.
 */
final class HolidayService
{
    /**
     * Booking's own capability (BookingService::CAPABILITY, kept as a copy
     * here since Scheduling cannot depend on Booking): a second capability
     * for one more admin screen would need granting separately for no
     * reason (progress notes, T3.5).
     */
    public const CAPABILITY = 'manage_bookings';

    public const MAX_RANGE_DAYS = 366;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly HolidayRepository $holidays,
    ) {
    }

    /**
     * @return list<Holiday>
     */
    public function holidays(Slug $calendar, LocalDate $from, LocalDate $to): array
    {
        $this->authorize();
        $days = $from->daysUntil($to);
        if ($days < 0 || $days >= self::MAX_RANGE_DAYS) {
            throw new InvalidValue('invalid_range', 'The range ends before it starts or is longer than a year.');
        }

        return $this->holidays->between($calendar, $from, $to);
    }

    public function save(Slug $calendar, LocalDate $date, Name $title): Holiday
    {
        $this->authorize();
        $holiday = new Holiday($calendar, $date, $title, HolidaySource::Manual);
        $this->holidays->save($holiday);

        return $holiday;
    }

    public function delete(Slug $calendar, LocalDate $date): void
    {
        $this->authorize();
        if ([] === $this->holidays->between($calendar, $date, $date)) {
            throw new NotFound('holiday_not_found', 'No holiday has this calendar and date.');
        }
        $this->holidays->delete($calendar, $date);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
