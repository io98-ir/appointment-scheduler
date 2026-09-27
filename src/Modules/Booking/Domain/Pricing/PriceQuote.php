<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

use Vaqtyar\Shared\Domain\InvalidValue;
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

    /**
     * A quote read back, e.g. from a hold: its lines as toArray() gave them.
     *
     * @param array<mixed> $data
     * @throws InvalidValue invalid_price_line
     */
    public static function fromArray(array $data): self
    {
        $lines = $data['lines'] ?? null;
        if (!\is_array($lines)) {
            throw new InvalidValue('invalid_price_line', 'A quote needs its lines.');
        }

        return new self(\array_values(\array_map(
            static fn (mixed $line): PriceLine => \is_array($line)
                ? PriceLine::fromArray($line)
                : throw new InvalidValue('invalid_price_line', 'A price line is an object.'),
            $lines
        )));
    }

    /**
     * The first line with $code, e.g. the coupon's.
     */
    public function lineOf(string $code): ?PriceLine
    {
        foreach ($this->lines as $line) {
            if ($code === $line->code) {
                return $line;
            }
        }

        return null;
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
