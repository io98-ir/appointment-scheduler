<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * What a customer pays online when they book, and whether they must
 * (booking-engine §7): the whole price, or a deposit that is a percent of it
 * or a fixed amount, the rest being paid at the place or later from the
 * customer panel. With $required the booking is refused unless it is paid
 * online; without it online payment stays the customer's choice.
 *
 * It applies to the customer's own booking from the widget. Staff book
 * without paying.
 */
final class DepositPolicy
{
    public const NONE = 'none';
    public const PERCENT = 'percent';
    public const FIXED = 'fixed';

    private const KINDS = [self::NONE, self::PERCENT, self::FIXED];

    /** Rials: well above any price, and small enough that no sum overflows. */
    private const MAX_FIXED = 1_000_000_000_000;

    /**
     * @param string $kind none (the whole price), percent or fixed.
     * @param int $value 1 to 100 for percent, rials for fixed; ignored for none.
     * @throws InvalidValue invalid_policy
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $value,
        public readonly bool $required,
    ) {
        if (!\in_array($kind, self::KINDS, true)) {
            throw new InvalidValue('invalid_policy', 'The deposit is none, a percent or a fixed amount.');
        }
        if (self::PERCENT === $kind && ($value < 1 || $value > 100)) {
            throw new InvalidValue('invalid_policy', 'A percent deposit is 1 to 100.');
        }
        if (self::FIXED === $kind && ($value < 1 || $value > self::MAX_FIXED)) {
            throw new InvalidValue('invalid_policy', 'A fixed deposit is a positive amount of rials.');
        }
    }

    /**
     * No deposit and no obligation: what a site without the policy does.
     */
    public static function lenient(): self
    {
        return new self(self::NONE, 0, false);
    }

    /**
     * The policies table's JSON config: {"kind": string, "value": int, "required": bool}.
     *
     * @param array<mixed> $config
     * @throws InvalidValue when a present key does not match this shape.
     */
    public static function fromConfig(array $config): self
    {
        $kind = $config['kind'] ?? self::NONE;
        $value = $config['value'] ?? 0;
        $required = $config['required'] ?? false;
        if (!\is_string($kind) || !\is_int($value) || !\is_bool($required)) {
            throw new InvalidValue('invalid_policy', 'The deposit policy has a value of the wrong type.');
        }

        return new self($kind, self::NONE === $kind ? 0 : $value, $required);
    }

    /**
     * @return array{kind: string, value: int, required: bool}
     */
    public function toConfig(): array
    {
        return ['kind' => $this->kind, 'value' => $this->value, 'required' => $this->required];
    }

    /**
     * Whether only part of the price is asked for when the customer pays online.
     */
    public function isDeposit(): bool
    {
        return self::NONE !== $this->kind;
    }

    /**
     * What is charged at the gateway when the customer pays online: the
     * deposit, never more than the price, never nothing for a price above
     * zero; the whole price without a deposit.
     */
    public function dueNow(Money $total): Money
    {
        if ($total->isZero() || $total->isNegative()) {
            return Money::zero();
        }
        $due = match ($this->kind) {
            self::PERCENT => $total->percent($this->value, Rounding::HalfUp),
            self::FIXED => Money::ofRial($this->value),
            default => $total,
        };
        if ($due->isGreaterThan($total)) {
            return $total;
        }

        return $due->isZero() ? Money::ofRial(1) : $due;
    }
}
