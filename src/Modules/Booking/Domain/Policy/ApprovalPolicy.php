<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Policy;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * Whether a customer's own booking waits for staff to approve it
 * (booking-engine §4). Its time is taken at once; staff accept it from the
 * appointment, or cancel it. Staff's own bookings are never held back.
 */
final class ApprovalPolicy
{
    public function __construct(public readonly bool $required)
    {
    }

    public static function lenient(): self
    {
        return new self(false);
    }

    /**
     * The policies table's JSON config: {"required": bool}.
     *
     * @param array<mixed> $config
     * @throws InvalidValue when the key holds something else.
     */
    public static function fromConfig(array $config): self
    {
        $required = $config['required'] ?? false;
        if (!\is_bool($required)) {
            throw new InvalidValue('invalid_policy', 'The approval policy has a value of the wrong type.');
        }

        return new self($required);
    }

    /**
     * @return array{required: bool}
     */
    public function toConfig(): array
    {
        return ['required' => $this->required];
    }
}
