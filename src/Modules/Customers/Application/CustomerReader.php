<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Application;

use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class CustomerReader implements CustomerApi
{
    /**
     * @param \Closure(): bool $requiresVerification Whether a booking needs a verified phone (T4.3).
     */
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly PhoneSessions $sessions,
        private readonly Clock $clock,
        private readonly \Closure $requiresVerification,
    ) {
    }

    public function canBook(int $customerId): bool
    {
        return $this->customers->find($customerId)?->canBook() ?? false;
    }

    public function customerOfSession(?string $sessionToken): ?int
    {
        $phone = $this->sessions->phoneOf($sessionToken, $this->clock->now()->getTimestamp());
        if (null === $phone) {
            return null;
        }
        $customer = $this->customers->findByPhone(PhoneNumber::fromInput($phone));

        return null !== $customer && $customer->canBook() ? $customer->id : null;
    }

    public function forBooking(
        string $phone,
        string $firstName,
        string $lastName,
        ?string $email,
        ?string $sessionToken = null,
    ): int {
        $number = PhoneNumber::fromInput($phone);
        $verified = $this->sessions->phoneOf($sessionToken, $this->clock->now()->getTimestamp()) === $number->e164;
        if (($this->requiresVerification)() && !$verified) {
            throw new InvalidValue('phone_not_verified', 'The phone number has not been verified.');
        }
        $existing = $this->customers->findByPhone($number);
        if (null !== $existing) {
            if (!$existing->canBook() || null === $existing->id) {
                throw new InvalidValue('customer_unavailable', 'The customer does not exist or is blocked.');
            }

            return $existing->id;
        }
        $saved = $this->customers->save(new Customer(
            null,
            null,
            \trim($firstName),
            \trim($lastName),
            $number,
            null === $email || '' === \trim($email) ? null : Email::fromInput($email)
        ));

        return $saved->id ?? throw new \LogicException('A saved customer has an id.');
    }
}
