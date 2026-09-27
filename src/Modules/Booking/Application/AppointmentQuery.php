<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;

/**
 * The read side of appointments for the admin (architecture §1): direct
 * SQL, no entities. Customers are left unnamed (AppointmentRow::$customer).
 */
interface AppointmentQuery
{
    /**
     * @param ?AppointmentSearch $search null searches nothing; one that
     *     matches nothing is the caller's to skip.
     * @return list<AppointmentRow>
     */
    public function list(
        AppointmentFilter $filter,
        ?AppointmentSearch $search,
        AppointmentSort $sort,
        int $offset,
        int $limit,
    ): array;

    public function count(AppointmentFilter $filter, ?AppointmentSearch $search): int;

    public function detail(int $id): ?AppointmentDetail;

    /**
     * The appointments that overlap [from, to), by start.
     *
     * @param int $from UTC seconds.
     * @param int $to UTC seconds.
     * @param list<int> $staffIds empty means everyone.
     * @param list<AppointmentStatus> $statuses
     * @param int $limit the first this many.
     * @return list<AppointmentRow>
     */
    public function between(
        int $from,
        int $to,
        array $staffIds,
        ?int $locationId,
        array $statuses,
        int $limit,
    ): array;
}
