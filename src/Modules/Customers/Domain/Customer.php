<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Domain;

use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\Domain\Ulid;

/**
 * A person who books. The phone number is the identity (one customer per
 * number, and the OTP login of T4.3 goes by it); a WordPress account is
 * optional.
 */
final class Customer
{
    /** Characters, the VARCHAR(100) name columns. */
    public const MAX_NAME_LENGTH = 100;

    /** Bytes, the TEXT column. */
    public const MAX_NOTE_LENGTH = 65535;

    public const MAX_TAGS = 20;

    /** Characters of one tag. */
    public const MAX_TAG_LENGTH = 50;

    /** @var list<string> */
    public readonly array $tags;

    /**
     * @param ?int $id null until stored.
     * @param ?Ulid $uuid The public id (ADR-013); the repository gives one on the first save.
     * @param ?int $wpUserId the WordPress account, when the customer has one.
     * @param list<string> $tags Trimmed; duplicates are dropped.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?Ulid $uuid,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly PhoneNumber $phone,
        public readonly ?Email $email = null,
        public readonly ?int $wpUserId = null,
        public readonly ?LocalDate $birthDate = null,
        public readonly string $note = '',
        array $tags = [],
        public readonly CustomerStatus $status = CustomerStatus::Active,
    ) {
        if ((null !== $id && $id < 1) || (null !== $wpUserId && $wpUserId < 1)) {
            throw new InvalidValue('invalid_id', 'An id must be positive.');
        }
        self::assertNamePart($firstName);
        self::assertNamePart($lastName);
        if ('' === $firstName && '' === $lastName) {
            throw new InvalidValue('invalid_name', 'A customer needs a first or a last name.');
        }
        if (\strlen($note) > self::MAX_NOTE_LENGTH || false === \mb_check_encoding($note, 'UTF-8')) {
            throw new InvalidValue('text_too_long', 'The note is too long.');
        }
        $this->tags = self::tags($tags);
    }

    public function fullName(): string
    {
        return \trim($this->firstName . ' ' . $this->lastName);
    }

    public function canBook(): bool
    {
        return CustomerStatus::Active === $this->status;
    }

    private static function assertNamePart(string $part): void
    {
        if (
            $part !== \trim($part)
            || false === \mb_check_encoding($part, 'UTF-8')
            || 1 === \preg_match('/\p{Cc}/u', $part)
            || \mb_strlen($part) > self::MAX_NAME_LENGTH
        ) {
            throw new InvalidValue('invalid_name', 'A name must be one trimmed line of up to 100 characters.');
        }
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private static function tags(array $tags): array
    {
        $clean = [];
        foreach ($tags as $tag) {
            $tag = \trim($tag);
            if (
                '' === $tag
                || false === \mb_check_encoding($tag, 'UTF-8')
                || 1 === \preg_match('/\p{Cc}/u', $tag)
                || \mb_strlen($tag) > self::MAX_TAG_LENGTH
            ) {
                throw new InvalidValue('invalid_tag', 'A tag must be one line of 1 to 50 characters.');
            }
            $clean[$tag] = true;
        }
        if (\count($clean) > self::MAX_TAGS) {
            throw new InvalidValue('too_many_tags', 'A customer has at most 20 tags.');
        }

        return \array_map(\strval(...), \array_keys($clean));
    }
}
