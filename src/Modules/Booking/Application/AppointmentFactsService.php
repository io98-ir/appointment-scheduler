<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Contracts\AppointmentFacts;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Catalog\Contracts\CatalogNames;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;

/**
 * AppointmentFactsReader over the appointment query and the two lookups
 * that name its parts.
 */
final class AppointmentFactsService implements AppointmentFactsReader
{
    public function __construct(
        private readonly AppointmentQuery $appointments,
        private readonly CustomerDirectory $customers,
        private readonly CatalogNames $names,
    ) {
    }

    public function find(int $appointmentId): ?AppointmentFacts
    {
        $detail = $this->appointments->detail($appointmentId);
        if (null === $detail) {
            return null;
        }
        $row = $detail->row;
        $customer = $this->customers->summaries([$row->customerId])[$row->customerId] ?? null;
        $gone = null === $customer || $customer->deleted;
        $staff = $this->names->staffContact($row->staffId);

        return new AppointmentFacts(
            $row->id,
            $row->code,
            $row->status->value,
            $row->start,
            $row->end,
            $row->timezone,
            $row->partySize,
            $row->total->amount,
            null === $customer ? '' : $customer->name,
            $gone ? null : $customer->phone,
            $gone ? null : $customer->email,
            $this->names->serviceName($row->serviceId) ?? '',
            $this->names->locationName($row->locationId) ?? '',
            $staff['name'] ?? '',
            $staff['email'] ?? null
        );
    }
}
