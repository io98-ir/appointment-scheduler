<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Scheduling\Contracts\BookingWindow;
use Vaqtyar\Modules\Scheduling\Contracts\BookingWindows;

/**
 * Availability's BookingWindows over the booking-window policy: a service's own over the global
 * one. Read once per service in a request, since availability asks on every view it builds.
 */
final class PolicyBookingWindows implements BookingWindows
{
    /** @var array<int, BookingWindow> */
    private array $read = [];

    public function __construct(private readonly TermsReader $terms)
    {
    }

    public function forService(int $serviceId): BookingWindow
    {
        if (!isset($this->read[$serviceId])) {
            $window = $this->terms->termsFor($serviceId)->window;
            $days = $window->maxAdvanceDays;
            $this->read[$serviceId] = new BookingWindow($window->minNoticeMin, null === $days ? null : $days * 1440);
        }

        return $this->read[$serviceId];
    }
}
