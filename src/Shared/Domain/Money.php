<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * An integer amount in the currency's smallest unit (ADR-010). Never a float:
 * an operation whose result does not fit in an int fails instead of turning
 * into one silently, as PHP arithmetic would.
 */
final class Money
{
    private function __construct(
        public readonly int $amount,
        public readonly Currency $currency,
    ) {
    }

    public static function ofRial(int $amount): self
    {
        return new self($amount, Currency::IRR);
    }

    public static function zero(): self
    {
        return self::ofRial(0);
    }

    /**
     * @param array<mixed> $data {"amount": int, "currency": "IRR"}, the API shape.
     */
    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? null;
        $currency = Currency::tryFrom(\is_string($data['currency'] ?? null) ? $data['currency'] : '');
        if (!\is_int($amount) || null === $currency) {
            throw new InvalidValue('invalid_money', 'Money needs an integer amount and a known currency.');
        }

        return new self($amount, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withAmount($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return $this->withAmount($this->amount - $other->amount);
    }

    public function multiply(int $factor): self
    {
        return $this->withAmount($this->amount * $factor);
    }

    /**
     * $percent of this amount, rounded to a whole unit as $rounding says.
     */
    public function percent(int $percent, Rounding $rounding): self
    {
        if ($percent < 0) {
            throw new InvalidValue('invalid_percent', 'A percentage cannot be negative.');
        }
        $scaled = $this->multiply($percent)->amount;

        $quotient = \intdiv($scaled, 100);
        $remainder = $scaled % 100;
        $awayFromZero = $scaled < 0 ? -1 : 1;
        $roundAway = match ($rounding) {
            Rounding::Down => false,
            Rounding::Up => 0 !== $remainder,
            Rounding::HalfUp => \abs($remainder) * 2 >= 100,
        };

        return new self($roundAway ? $quotient + $awayFromZero : $quotient, $this->currency);
    }

    public function equals(self $other): bool
    {
        // Only IRR exists yet; the ignore turns into an error when a second currency is added.
        return $this->currency === $other->currency // @phpstan-ignore identical.alwaysTrue
            && $this->amount === $other->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency->value];
    }

    private function withAmount(int|float $amount): self
    {
        // int arithmetic that overflows yields a float in PHP.
        if (!\is_int($amount)) {
            throw new InvalidValue('money_overflow', 'The amount is too large.');
        }

        return new self($amount, $this->currency);
    }

    /**
     * Only IRR exists yet, so PHPStan sees this as a no-op; the ignores turn
     * into errors when a second currency is added and the check becomes live.
     */
    private function assertSameCurrency(self $other): void // @phpstan-ignore void.pure
    {
        if ($this->currency !== $other->currency) { // @phpstan-ignore notIdentical.alwaysFalse
            throw new InvalidValue('currency_mismatch', 'Cannot combine amounts in different currencies.');
        }
    }
}
