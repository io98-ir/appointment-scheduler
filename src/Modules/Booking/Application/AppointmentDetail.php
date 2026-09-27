<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;

/**
 * One appointment with all the admin sees of it: its row, the price as it
 * was sold, extras, custom field answers and history, oldest first.
 *
 * @phpstan-type Extra array{extra_id: int, qty: int, unit_price: int}
 * @phpstan-type Change array{
 *     action: string,
 *     from: ?AppointmentStatus,
 *     to: ?AppointmentStatus,
 *     changes: array<mixed>,
 *     actor_type: string,
 *     actor_id: ?int,
 *     reason: ?string,
 *     at: int
 * }
 */
final class AppointmentDetail
{
    /**
     * @param list<Extra> $extras unit prices in IRR.
     * @param array<string, string> $answers by field key.
     * @param list<Change> $history times in UTC seconds.
     * @param int $createdAt UTC seconds.
     * @param ?int $cancelledAt UTC seconds.
     */
    public function __construct(
        public readonly AppointmentRow $row,
        public readonly string $uuid,
        public readonly string $source,
        public readonly PriceQuote $quote,
        public readonly string $customerNote,
        public readonly string $internalNote,
        public readonly array $extras,
        public readonly array $answers,
        public readonly array $history,
        public readonly ?int $createdBy,
        public readonly int $createdAt,
        public readonly ?int $cancelledAt,
        public readonly ?string $cancelReason,
    ) {
    }

    public function withRow(AppointmentRow $row): self
    {
        return new self(
            $row,
            $this->uuid,
            $this->source,
            $this->quote,
            $this->customerNote,
            $this->internalNote,
            $this->extras,
            $this->answers,
            $this->history,
            $this->createdBy,
            $this->createdAt,
            $this->cancelledAt,
            $this->cancelReason
        );
    }
}
