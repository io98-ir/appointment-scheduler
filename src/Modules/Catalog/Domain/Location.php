<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * A branch. Its timezone is where its local dates and working hours are
 * reckoned; with a single location the admin UI hides the concept.
 */
final class Location
{
    use GuardsStoredNumbers;

    /** Bytes, the TEXT column. */
    public const MAX_ADDRESS_LENGTH = 65535;

    /**
     * @param ?int $id null until stored.
     * @param ?Slug $holidayCalendar the holiday calendar (Scheduling, T1.3) its dates are off by.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly \DateTimeZone $timezone,
        public readonly string $address = '',
        public readonly ?PhoneNumber $phone = null,
        public readonly ?Slug $holidayCalendar = null,
        public readonly Status $status = Status::Active,
        public readonly int $sort = 0,
    ) {
        self::assertIds($id);
        self::assertSort($sort);
        // Region names and UTC only. A fixed offset ("+03:30", "Etc/GMT-3", "EST") has
        // no rules, so it stays wrong when the region changes its clocks; the
        // backward-compatible aliases ("Iran") are left out with the Etc zones.
        if (!\in_array($timezone->getName(), \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidValue('invalid_timezone', 'A location needs a named timezone such as Asia/Tehran.');
        }
        if (\strlen($address) > self::MAX_ADDRESS_LENGTH) {
            throw new InvalidValue('text_too_long', 'The address is too long.');
        }
    }
}
