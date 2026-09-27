<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Page;
use Vaqtyar\Shared\Domain\PersianDigits;

/**
 * The admin's reads over appointments: the list, one appointment and the
 * calendar. Each checks the bookings capability again, after the REST
 * permission callback (architecture §12), and names the customers with one
 * lookup, since their table is the Customers module's.
 */
final class AppointmentBrowser
{
    /** Six weeks: a month view with the days around it. */
    public const MAX_CALENDAR_DAYS = 42;

    /**
     * The calendar returns at most this many, unpaged (principles §8); a
     * busier range is refused, and the admin narrows it by staff or days.
     */
    public const MAX_CALENDAR_ITEMS = 2_000;

    /**
     * A search looks at this many of the newest matching customers; a
     * longer name or the phone number narrows it.
     */
    public const SEARCH_CUSTOMERS = 200;

    /** What takes a staff member's time; cancelled and expired ones do not. */
    private const CALENDAR_STATUSES = [
        AppointmentStatus::PendingApproval,
        AppointmentStatus::PendingPayment,
        AppointmentStatus::Confirmed,
        AppointmentStatus::Completed,
        AppointmentStatus::NoShow,
    ];

    public function __construct(
        private readonly AppointmentQuery $appointments,
        private readonly CustomerDirectory $customers,
        private readonly Authorizer $authorizer,
    ) {
    }

    /**
     * @param string $search as typed: a tracking code, or part of a
     *     customer's name, email or phone number.
     * @return Page<AppointmentRow>
     * @throws InvalidValue invalid_range when the dates are out of order.
     */
    public function list(
        AppointmentFilter $filter,
        string $search,
        AppointmentSort $sort,
        int $offset,
        int $limit,
    ): Page {
        $this->authorize();
        if (null !== $filter->from && null !== $filter->to && $filter->to->isBefore($filter->from)) {
            throw new InvalidValue('invalid_range', 'The first date is after the last.');
        }
        $matched = $this->search($search);
        if (null !== $matched && $matched->matchesNothing()) {
            return new Page([], 0);
        }

        return new Page(
            $this->named($this->appointments->list($filter, $matched, $sort, $offset, $limit)),
            $this->appointments->count($filter, $matched)
        );
    }

    /**
     * @throws NotFound appointment_not_found
     */
    public function appointment(int $id): AppointmentDetail
    {
        $this->authorize();
        $detail = $this->appointments->detail($id)
            ?? throw new NotFound('appointment_not_found', 'No appointment has this id.');

        return $detail->withRow($this->named([$detail->row])[0]);
    }

    /**
     * The appointments that take time in [from, to), by start.
     *
     * @param int $from UTC seconds.
     * @param int $to UTC seconds.
     * @param list<int> $staffIds empty means everyone.
     * @return list<AppointmentRow>
     * @throws InvalidValue invalid_range, range_too_long (MAX_CALENDAR_DAYS)
     *     or too_many_appointments (MAX_CALENDAR_ITEMS).
     */
    public function calendar(int $from, int $to, array $staffIds, ?int $locationId): array
    {
        $this->authorize();
        if ($to <= $from) {
            throw new InvalidValue('invalid_range', 'The range ends before it starts.');
        }
        if ($to - $from > self::MAX_CALENDAR_DAYS * 86_400) {
            throw new InvalidValue('range_too_long', 'The calendar shows six weeks at most.');
        }
        $staffIds = \array_values(\array_unique($staffIds));
        \sort($staffIds);

        $rows = $this->appointments->between(
            $from,
            $to,
            $staffIds,
            $locationId,
            self::CALENDAR_STATUSES,
            self::MAX_CALENDAR_ITEMS + 1
        );
        if (\count($rows) > self::MAX_CALENDAR_ITEMS) {
            throw new InvalidValue('too_many_appointments', 'The range has too many appointments; narrow it.');
        }

        return $this->named($rows);
    }

    /**
     * Null for a blank search. A search that spells a tracking code looks
     * for it too, with Persian digits read as Latin.
     */
    private function search(string $search): ?AppointmentSearch
    {
        $search = \trim($search);
        if ('' === $search) {
            return null;
        }
        try {
            $code = TrackingCode::fromString(PersianDigits::toLatin($search))->value;
        } catch (InvalidValue) {
            $code = null;
        }

        return new AppointmentSearch($code, $this->customers->matching($search, self::SEARCH_CUSTOMERS));
    }

    /**
     * @param list<AppointmentRow> $rows
     * @return list<AppointmentRow>
     */
    private function named(array $rows): array
    {
        if ([] === $rows) {
            return [];
        }
        $customers = $this->customers->summaries(
            \array_values(\array_unique(\array_map(static fn (AppointmentRow $row): int => $row->customerId, $rows)))
        );

        return \array_map(
            static fn (AppointmentRow $row): AppointmentRow => $row->withCustomer($customers[$row->customerId] ?? null),
            $rows
        );
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(BookingService::CAPABILITY)) {
            throw new Forbidden(BookingService::CAPABILITY);
        }
    }
}
