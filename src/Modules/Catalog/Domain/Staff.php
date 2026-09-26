<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * A person who serves bookings. Each has one busy calendar, whatever the
 * service or location (booking-engine §1).
 */
final class Staff
{
    use GuardsStoredNumbers;

    /** Characters, the VARCHAR(191) column. */
    public const MAX_TITLE_LENGTH = 191;

    /** Bytes, the TEXT column. */
    public const MAX_BIO_LENGTH = 65535;

    /**
     * @param ?int $id null until stored.
     * @param ?int $wpUserId the WordPress account, for the staff panel; none for staff who never log in.
     * @param ?int $locationId the home location; null serves every location.
     * @param ?int $avatarId a media library attachment.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly Color $color,
        public readonly ?int $wpUserId = null,
        public readonly ?int $locationId = null,
        public readonly string $title = '',
        public readonly ?Email $email = null,
        public readonly ?PhoneNumber $phone = null,
        public readonly ?int $avatarId = null,
        public readonly string $bio = '',
        public readonly Status $status = Status::Active,
        public readonly int $sort = 0,
    ) {
        self::assertIds($id, $wpUserId, $locationId, $avatarId);
        self::assertSort($sort);
        if (\mb_strlen($title) > self::MAX_TITLE_LENGTH || \strlen($bio) > self::MAX_BIO_LENGTH) {
            throw new InvalidValue('text_too_long', 'The title or the bio is too long.');
        }
    }
}
