<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Infrastructure;

use Vaqtyar\Kernel\Settings\SettingsGroup;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * How a booking's total is rounded (booking-engine §5, step 7). The UI comes
 * with the settings screen (T6.1).
 */
final class PricingSettings implements SettingsGroup
{
    private const MAX_STEP = 10_000_000;

    private const ROUNDINGS = ['down' => Rounding::Down, 'up' => Rounding::Up, 'half_up' => Rounding::HalfUp];

    /**
     * @param int $roundingStep rials, 1 to 10,000,000; 1 leaves the total as it is.
     */
    public function __construct(
        public readonly int $roundingStep = 1,
        public readonly Rounding $rounding = Rounding::HalfUp,
    ) {
    }

    public static function name(): string
    {
        return 'pricing';
    }

    public static function autoload(): bool
    {
        return false;
    }

    /**
     * @param array<mixed> $stored
     */
    public static function fromStored(array $stored): static
    {
        $step = $stored['rounding_step'] ?? null;
        $rounding = $stored['rounding'] ?? null;
        $defaults = new self();

        return new self(
            \is_int($step) && $step >= 1 && $step <= self::MAX_STEP ? $step : $defaults->roundingStep,
            (\is_string($rounding) ? self::ROUNDINGS[$rounding] ?? null : null) ?? $defaults->rounding
        );
    }

    /**
     * @return array{rounding_step: int, rounding: string}
     */
    public function toStored(): array
    {
        return [
            'rounding_step' => $this->roundingStep,
            'rounding' => (string) \array_search($this->rounding, self::ROUNDINGS, true),
        ];
    }
}
