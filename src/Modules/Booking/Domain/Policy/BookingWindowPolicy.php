<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * How soon and how far ahead one service can be booked, in place of the
 * site's own rules (Scheduling's BookingRules). A part left out (null)
 * keeps the site's value.
 */
final class BookingWindowPolicy
{
    public const MAX_NOTICE_MIN = 365 * 1440;
    public const MAX_ADVANCE_DAYS = 730;

    /**
     * @param ?int $minNoticeMin booking closes this many minutes before the start; null keeps the site's.
     * @param ?int $maxAdvanceDays booking opens this many days ahead; null keeps the site's.
     * @throws InvalidValue invalid_policy
     */
    public function __construct(public readonly ?int $minNoticeMin, public readonly ?int $maxAdvanceDays)
    {
        if (null !== $minNoticeMin && ($minNoticeMin < 0 || $minNoticeMin > self::MAX_NOTICE_MIN)) {
            throw new InvalidValue('invalid_policy', 'The minimum notice is 0 minutes to a year.');
        }
        if (null !== $maxAdvanceDays && ($maxAdvanceDays < 1 || $maxAdvanceDays > self::MAX_ADVANCE_DAYS)) {
            throw new InvalidValue('invalid_policy', 'Booking can be open 1 to 730 days ahead.');
        }
    }

    public static function lenient(): self
    {
        return new self(null, null);
    }

    /**
     * The policies table's JSON config: {"min_notice_min": int|null, "max_advance_days": int|null}.
     *
     * @param array<mixed> $config
     * @throws InvalidValue when a present key does not match this shape.
     */
    public static function fromConfig(array $config): self
    {
        return new self(self::intOrNull($config, 'min_notice_min'), self::intOrNull($config, 'max_advance_days'));
    }

    /**
     * @return array{min_notice_min: ?int, max_advance_days: ?int}
     */
    public function toConfig(): array
    {
        return ['min_notice_min' => $this->minNoticeMin, 'max_advance_days' => $this->maxAdvanceDays];
    }

    /**
     * @param array<mixed> $config
     */
    private static function intOrNull(array $config, string $key): ?int
    {
        $value = $config[$key] ?? null;
        if (null !== $value && !\is_int($value)) {
            throw new InvalidValue('invalid_policy', "The policy's {$key} is not a whole number.");
        }

        return $value;
    }
}
