<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Until when, and how many times, a customer may move an appointment
 * (booking-engine §6), e.g. up to 12 hours before, twice at most.
 */
final class ReschedulePolicy
{
    /**
     * @param ?int $noticeHours null is any time before the start.
     * @param ?int $maxTimes null is no limit.
     */
    public function __construct(public readonly ?int $noticeHours, public readonly ?int $maxTimes)
    {
        if ((null !== $noticeHours && $noticeHours < 0) || (null !== $maxTimes && $maxTimes < 0)) {
            throw new InvalidValue('invalid_policy', 'The notice and the limit are 0 or more.');
        }
    }

    public static function lenient(): self
    {
        return new self(null, null);
    }

    /**
     * @param int $start the current start, UTC seconds.
     * @param int $times how often the appointment was moved already.
     */
    public function decide(int $start, int $now, int $times): Decision
    {
        $left = $start - $now;
        if ($left <= 0) {
            return Decision::deny('policy.already_started');
        }
        if (null !== $this->noticeHours && $left < $this->noticeHours * 3600) {
            return Decision::deny('policy.reschedule_window_passed');
        }
        if (null !== $this->maxTimes && $times >= $this->maxTimes) {
            return Decision::deny('policy.reschedule_limit_reached');
        }

        return Decision::allow();
    }
}
