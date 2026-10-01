<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Waitlist;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * A customer's request to be told when a time opens on a day for a service
 * at a location, with one staff member or any. It is not a reservation:
 * when a time opens, whoever books it first has it.
 */
final class WaitlistEntry
{
    public const URL_MAX = 500;

    /**
     * @param ?int $id null until stored.
     * @param ?int $staffId null when any staff member will do.
     * @param string $pageUrl the page of the site with the booking form, where the message sends them.
     * @throws InvalidValue invalid_waitlist_page when the address is not an http(s) one of reasonable size.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $customerId,
        public readonly int $locationId,
        public readonly int $variantId,
        public readonly ?int $staffId,
        public readonly LocalDate $date,
        public readonly string $pageUrl,
        public readonly WaitlistStatus $status = WaitlistStatus::Waiting,
        public readonly ?int $notifiedAt = null,
        public readonly int $createdAt = 0,
    ) {
        if (
            \strlen($pageUrl) > self::URL_MAX
            || 1 !== \preg_match('#^https?://[\x21-\x7E]+$#', $pageUrl)
        ) {
            throw new InvalidValue('invalid_waitlist_page', 'The page is an http or https address.');
        }
    }
}
