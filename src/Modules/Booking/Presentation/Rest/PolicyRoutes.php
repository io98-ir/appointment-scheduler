<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\PolicyAdminService;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\RefundTier;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;

/**
 * The admin policy API (docs/api.md): the cancellation, reschedule, deposit,
 * approval or booking-window policy of a service, or the global one at service_id 0.
 *
 *     GET    /policies/{type}/{service_id}   the stored config, or null when not set
 *     PUT    /policies/{type}/{service_id}   upsert: 200 with the saved config
 *     DELETE /policies/{type}/{service_id}   clear it, back to the level below: 204
 *
 * type is one of "cancellation", "reschedule", "deposit", "approval" and
 * "booking_window". Each needs the booking capability; PolicyAdminService
 * checks it again.
 */
final class PolicyRoutes
{
    private const TYPES = ['cancellation', 'reschedule', 'deposit', 'approval', 'booking_window'];

    /** A refund ladder of a few tiers, with room to spare. */
    private const MAX_TIERS = 50;

    /**
     * @param \Closure(): PolicyAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(PolicyAdminService::CAPABILITY));
        $path = '/policies/(?P<type>' . \implode('|', self::TYPES) . ')/(?P<service_id>\d+)';
        $args = [
            'type' => ['type' => 'string', 'enum' => self::TYPES, 'required' => true],
            'service_id' => ['type' => 'integer', 'minimum' => 0, 'required' => true],
        ];
        $optionalHours = ['type' => ['integer', 'null'], 'minimum' => 0, 'default' => null];

        $this->router->add(
            $path,
            'GET',
            function (\WP_REST_Request $request): array {
                $type = self::type($request);
                if (\in_array($type, PolicyAdminService::TERMS_TYPES, true)) {
                    return ['config' => ($this->service)()->terms($type, self::serviceId($request))];
                }

                return self::json(
                    'cancellation' === $type
                        ? ($this->service)()->cancellation(self::serviceId($request))
                        : ($this->service)()->reschedule(self::serviceId($request))
                );
            },
            $allowed,
            $args
        );
        $this->router->add(
            $path,
            'PUT',
            function (\WP_REST_Request $request): array {
                $type = self::type($request);
                if (\in_array($type, PolicyAdminService::TERMS_TYPES, true)) {
                    return ['config' => ($this->service)()->saveTerms(
                        $type,
                        self::serviceId($request),
                        self::termsFromRequest($request, $type)
                    )];
                }

                return self::json(
                    'cancellation' === $type
                        ? ($this->service)()->saveCancellation(
                            self::serviceId($request),
                            self::cancellationFromRequest($request)
                        )
                        : ($this->service)()->saveReschedule(
                            self::serviceId($request),
                            self::rescheduleFromRequest($request)
                        )
                );
            },
            $allowed,
            $args + [
                // deposit: what is paid online when booking, and whether it must be.
                'kind' => ['type' => 'string', 'enum' => ['none', 'percent', 'fixed'], 'default' => 'none'],
                'value' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                // deposit and approval.
                'required' => ['type' => 'boolean', 'default' => false],
                // booking_window: null keeps the site's rule.
                'min_notice_min' => ['type' => ['integer', 'null'], 'minimum' => 0, 'default' => null],
                'max_advance_days' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
                'notice_hours' => $optionalHours,
                'refund' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_TIERS,
                    'default' => [],
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'hours' => ['type' => 'integer', 'minimum' => 0, 'required' => true],
                            'percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'required' => true],
                        ],
                    ],
                ],
                'max_times' => ['type' => ['integer', 'null'], 'minimum' => 0, 'default' => null],
            ]
        );
        $this->router->add(
            $path,
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                $type = self::type($request);
                if (\in_array($type, PolicyAdminService::TERMS_TYPES, true)) {
                    ($this->service)()->deleteTerms($type, self::serviceId($request));
                } elseif ('cancellation' === $type) {
                    ($this->service)()->deleteCancellation(self::serviceId($request));
                } else {
                    ($this->service)()->deleteReschedule(self::serviceId($request));
                }

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            $args
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function cancellationFromRequest(\WP_REST_Request $request): CancellationPolicy
    {
        $tiers = \array_map(
            static fn (array $tier): RefundTier => new RefundTier(
                self::intValue($tier['hours'] ?? null),
                self::intValue($tier['percent'] ?? null)
            ),
            self::objects($request->get_param('refund'))
        );

        return new CancellationPolicy(self::optionalInt($request->get_param('notice_hours')), $tiers);
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function rescheduleFromRequest(\WP_REST_Request $request): ReschedulePolicy
    {
        return new ReschedulePolicy(
            self::optionalInt($request->get_param('notice_hours')),
            self::optionalInt($request->get_param('max_times'))
        );
    }

    /**
     * @return array{config: array<mixed>|null}
     */
    private static function json(CancellationPolicy|ReschedulePolicy|null $policy): array
    {
        return ['config' => $policy?->toConfig()];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function type(\WP_REST_Request $request): string
    {
        return self::string($request->get_url_params()['type'] ?? null);
    }

    /**
     * The config a deposit, approval or booking-window policy is made from; the domain checks it.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @return array<string, mixed>
     */
    private static function termsFromRequest(\WP_REST_Request $request, string $type): array
    {
        return match ($type) {
            'deposit' => [
                'kind' => self::string($request->get_param('kind')),
                'value' => self::intValue($request->get_param('value')),
                'required' => true === $request->get_param('required'),
            ],
            'approval' => ['required' => true === $request->get_param('required')],
            default => [
                'min_notice_min' => self::optionalInt($request->get_param('min_notice_min')),
                'max_advance_days' => self::optionalInt($request->get_param('max_advance_days')),
            ],
        };
    }

    /**
     * From the path only, as ScheduleRoutes does: the body may carry its own.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function serviceId(\WP_REST_Request $request): int
    {
        return self::intValue($request->get_url_params()['service_id'] ?? null);
    }

    /**
     * @return list<array<mixed>>
     */
    private static function objects(mixed $value): array
    {
        return \is_array($value)
            ? \array_values(\array_map(
                static fn (mixed $item): array => \is_array($item) ? $item : throw self::wrongType(),
                $value
            ))
            : throw self::wrongType();
    }

    private static function optionalInt(mixed $value): ?int
    {
        return null === $value ? null : self::intValue($value);
    }

    /**
     * The schema has checked the type; path parameters arrive as strings.
     */
    private static function intValue(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw self::wrongType();
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw self::wrongType();
    }

    private static function wrongType(): \LogicException
    {
        return new \LogicException('The schema let a wrong type through.');
    }
}
