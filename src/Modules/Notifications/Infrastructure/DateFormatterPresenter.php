<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure;

use Vaqtyar\Modules\Booking\Contracts\AppointmentFacts;
use Vaqtyar\Modules\Notifications\Application\FactsPresenter;
use Vaqtyar\Shared\DateFormatter;

/**
 * FactsPresenter in the site's calendar and digits. The total is a plain
 * number of rials with thousands separators: the template says the unit.
 */
final class DateFormatterPresenter implements FactsPresenter
{
    public function __construct(private readonly DateFormatter $formatter)
    {
    }

    /**
     * @return array<string, string>
     */
    public function values(AppointmentFacts $facts): array
    {
        try {
            $zone = new \DateTimeZone($facts->timezone);
        } catch (\Exception) {
            $zone = new \DateTimeZone('UTC');
        }
        $start = new \DateTimeImmutable('@' . $facts->start);
        $end = new \DateTimeImmutable('@' . $facts->end);

        return [
            'code' => $facts->code,
            'customer_name' => $facts->customerName,
            'service' => $facts->serviceName,
            'staff' => $facts->staffName,
            'location' => $facts->locationName,
            'date' => $this->formatter->longDate($start, $zone),
            'time' => $this->formatter->time($start, $zone),
            'end_time' => $this->formatter->time($end, $zone),
            'party_size' => $this->formatter->digits((string) $facts->partySize),
            'total' => $this->formatter->digits(\number_format($facts->total)),
        ];
    }
}
