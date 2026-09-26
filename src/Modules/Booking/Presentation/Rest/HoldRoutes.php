<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use DateTimeImmutable;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\HoldService;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * POST /holds (docs/api.md): the booking widget keeps a slot while the
 * customer fills the form. Public, so the site's REST nonce and a rate
 * limit per client stand in for a login.
 */
final class HoldRoutes
{
    /** Per client and minute: a few tries a customer, and customers behind one carrier NAT. */
    private const LIMIT = 30;

    private const ARGS = [
        'variant' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'location' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'start' => ['type' => 'string', 'format' => 'date-time', 'required' => true],
        'staff' => ['type' => 'integer', 'minimum' => 1],
        'extras' => [
            'type' => 'array',
            'items' => ['type' => 'integer', 'minimum' => 1],
            'maxItems' => 100,
            'default' => [],
        ],
        'party_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1],
    ];

    /**
     * @param \Closure(): HoldService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $this->router->add(
            '/holds',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->place($request),
            Router::hasRestNonce(...),
            self::ARGS,
            new RateLimit(self::LIMIT, 60)
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function place(\WP_REST_Request $request): \WP_REST_Response
    {
        $start = self::start($request->get_param('start'));
        $staff = $request->get_param('staff');
        $placed = ($this->service)()->place(
            new AvailabilityQuery(
                self::int($request->get_param('variant')),
                self::int($request->get_param('location')),
                null === $staff ? null : self::int($staff),
                \array_map(self::int(...), \array_values((array) $request->get_param('extras'))),
                self::int($request->get_param('party_size'))
            ),
            $start->getTimestamp()
        );
        $zone = $start->getTimezone();
        $time = static fn (int $timestamp): string => (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone($zone)
            ->format(\DATE_ATOM);

        return new \WP_REST_Response([
            'token' => $placed->token,
            'expires_at' => $time($placed->hold->expiresAt),
            'staff_id' => $placed->hold->staffId,
            'start' => $time($placed->hold->start),
            'end' => $time($placed->hold->end),
        ], 201);
    }

    /**
     * An ISO-8601 time with its offset, e.g. a start from GET /availability.
     */
    private static function start(mixed $value): DateTimeImmutable
    {
        $start = \is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value) : false;
        if (false === $start) {
            throw new InvalidValue('invalid_start', 'The start is a time with its offset, as availability gives it.');
        }

        return $start;
    }

    /**
     * The route's schema has made it an integer.
     */
    private static function int(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw new \LogicException('Not an integer.');
    }
}
