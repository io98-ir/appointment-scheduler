<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

/**
 * Who asks for a change: a staff member (a WordPress user) or a customer,
 * who may only touch their own appointments. Recorded in appointment_history.
 */
final class Actor
{
    public const USER = 'user';
    public const CUSTOMER = 'customer';
    public const SYSTEM = 'system';

    private function __construct(public readonly string $type, public readonly int $id)
    {
    }

    public static function user(int $userId): self
    {
        return new self(self::USER, $userId);
    }

    public static function customer(int $customerId): self
    {
        return new self(self::CUSTOMER, $customerId);
    }

    /**
     * A job or a payment callback: nobody in particular, recorded with no actor id.
     */
    public static function system(): self
    {
        return new self(self::SYSTEM, 0);
    }

    public function isCustomer(): bool
    {
        return self::CUSTOMER === $this->type;
    }
}
