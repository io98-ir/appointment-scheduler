<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use DateTimeZone;
use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Domain\HoldToken;

/**
 * POST /bookings (docs/api.md): staff confirm a hold as an appointment for
 * a customer. The customer's own booking from the widget comes with T4.2.
 */
final class BookingRoutes
{
    private const ARGS = [
        'hold_token' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{43}$', 'required' => true],
        'customer_id' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'customer_note' => ['type' => 'string', 'maxLength' => 2000, 'default' => ''],
    ];

    /**
     * @param \Closure(): BookingService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/bookings',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->confirm($request),
            static fn (): bool => \current_user_can(Caps::name(BookingService::CAPABILITY)),
            self::ARGS
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function confirm(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $request->get_param('hold_token');
        $customer = $request->get_param('customer_id');
        $note = $request->get_param('customer_note');
        $booked = ($this->service)()->confirm(
            HoldToken::fromString(\is_string($token) ? $token : ''),
            \is_int($customer) || \is_numeric($customer) ? (int) $customer : 0,
            \is_string($note) ? \sanitize_textarea_field($note) : '',
            \get_current_user_id()
        );
        $appointment = $booked->appointment;
        $zone = new DateTimeZone($appointment->timezone);
        $time = static fn (int $timestamp): string => (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($zone)
            ->format(\DATE_ATOM);

        return new \WP_REST_Response([
            'id' => $booked->id,
            'uuid' => $appointment->uuid->toString(),
            'code' => $appointment->code->value,
            'status' => $appointment->status()->value,
            'payment_status' => $appointment->paymentStatus->value,
            'staff_id' => $appointment->staffId,
            'start' => $time($appointment->start),
            'end' => $time($appointment->end),
            'price' => $appointment->quote->toArray(),
        ], 201);
    }
}
