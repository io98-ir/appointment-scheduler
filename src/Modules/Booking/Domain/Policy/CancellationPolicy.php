<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * Until when a customer may cancel, and how much of what they paid comes
 * back (booking-engine §6), e.g. up to 24 hours before; 100% from 48 hours
 * before, 50% from 24.
 */
final class CancellationPolicy
{
    /** @var list<RefundTier> Most hours first. */
    public readonly array $tiers;

    /**
     * @param ?int $noticeHours the latest a cancellation is allowed, in
     *     hours before the start; null is any time before it.
     * @param list<RefundTier> $tiers in any order; none refunds nothing.
     */
    public function __construct(public readonly ?int $noticeHours, array $tiers)
    {
        if (null !== $noticeHours && $noticeHours < 0) {
            throw new InvalidValue('invalid_policy', 'The notice is 0 hours or more.');
        }
        \usort($tiers, static fn (RefundTier $a, RefundTier $b): int => $b->hours <=> $a->hours);
        $this->tiers = $tiers;
    }

    /**
     * Any time before the start, everything back: when no policy is set.
     */
    public static function lenient(): self
    {
        return new self(null, [new RefundTier(0, 100)]);
    }

    /**
     * The policies table's JSON config: {"notice_hours": int|null,
     * "refund": [{"hours": int, "percent": int}]}. A missing "refund" is no
     * tiers, not an error. The config is all or nothing: a broken "refund"
     * throws even next to a valid "notice_hours", so WpdbPolicyReader skips
     * the whole row rather than keep only the valid half.
     *
     * @param array<mixed> $config
     * @throws InvalidValue when a present key does not match this shape.
     */
    public static function fromConfig(array $config): self
    {
        $refund = $config['refund'] ?? [];
        if (!\is_array($refund)) {
            throw new InvalidValue('invalid_policy', "The policy's refund is not a list.");
        }
        $tiers = [];
        foreach ($refund as $tier) {
            $hours = \is_array($tier) ? ($tier['hours'] ?? null) : null;
            $percent = \is_array($tier) ? ($tier['percent'] ?? null) : null;
            if (!\is_int($hours) || !\is_int($percent)) {
                throw new InvalidValue('invalid_policy', 'A refund tier needs whole-number hours and percent.');
            }
            $tiers[] = new RefundTier($hours, $percent);
        }

        return new self(self::intOrNull($config, 'notice_hours'), $tiers);
    }

    /**
     * @return array{notice_hours: ?int, refund: list<array{hours: int, percent: int}>}
     */
    public function toConfig(): array
    {
        return [
            'notice_hours' => $this->noticeHours,
            'refund' => \array_map(
                static fn (RefundTier $tier): array => ['hours' => $tier->hours, 'percent' => $tier->percent],
                $this->tiers
            ),
        ];
    }

    /**
     * @param int $start UTC seconds.
     * @param int $now UTC seconds.
     * @param Money $paid what the customer paid for the appointment.
     */
    public function decide(int $start, int $now, Money $paid): Decision
    {
        $left = $start - $now;
        if ($left <= 0) {
            return Decision::deny('policy.already_started');
        }
        if (null !== $this->noticeHours && $left < $this->noticeHours * 3600) {
            return Decision::deny('policy.cancel_window_passed');
        }
        $percent = 0;
        foreach ($this->tiers as $tier) {
            if ($left >= $tier->hours * 3600) {
                $percent = $tier->percent;
                break;
            }
        }

        // Half up: neither side loses a rounding rial on purpose.
        return Decision::allow($percent, $paid->percent($percent, Rounding::HalfUp));
    }

    /**
     * @param array<mixed> $config
     * @throws InvalidValue when the key holds something else.
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
