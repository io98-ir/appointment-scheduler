<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\Money;

/**
 * A price as the lines that make it up; the total is their sum. Taken at
 * hold time and kept with the booking, so a later change of prices does
 * not touch it (booking-engine §5).
 */
final class PriceQuote
{
    /**
     * @param list<PriceLine> $lines
     */
    private function __construct(public readonly array $lines)
    {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function with(PriceLine $line): self
    {
        return new self([...$this->lines, $line]);
    }

    public function total(): Money
    {
        return \array_reduce(
            $this->lines,
            static fn (Money $sum, PriceLine $line): Money => $sum->add($line->amount),
            Money::zero()
        );
    }

    /**
     * The sum of the lines with $code.
     */
    public function sumOf(string $code): Money
    {
        return \array_reduce(
            $this->lines,
            static fn (Money $sum, PriceLine $line): Money => $code === $line->code ? $sum->add($line->amount) : $sum,
            Money::zero()
        );
    }

    /**
     * @return array{total: array{amount: int, currency: string}, lines: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total()->toArray(),
            'lines' => \array_map(static fn (PriceLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}
