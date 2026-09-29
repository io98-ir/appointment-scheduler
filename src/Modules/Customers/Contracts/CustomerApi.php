<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Contracts;

/**
 * What other modules may ask of Customers.
 */
interface CustomerApi
{
    /**
     * Whether the customer exists, is not deleted and is not blocked.
     */
    public function canBook(int $customerId): bool;

    /**
     * The customer who books from the widget, found by phone number or made
     * on the spot (T4.2). A customer who exists keeps their own name and
     * email: a guest cannot rewrite them. The caller has already checked the
     * request came from the site (nonce and rate limit); proving the phone
     * belongs to the guest is the OTP of T4.3.
     *
     * @return int the customer id.
     * @throws \Vaqtyar\Shared\Domain\InvalidValue invalid_phone, invalid_email,
     *     invalid_name, or customer_unavailable when the customer is blocked.
     */
    public function forBooking(string $phone, string $firstName, string $lastName, ?string $email): int;
}
