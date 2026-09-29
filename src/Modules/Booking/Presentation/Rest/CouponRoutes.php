<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\CouponAdminService;
use Vaqtyar\Modules\Booking\Domain\Pricing\Coupon;
use Vaqtyar\Modules\Booking\Domain\Pricing\CouponType;

/**
 * The admin coupons API (docs/api.md):
 *
 *     GET    /coupons        every coupon, newest first
 *     POST   /coupons        create: 201 with the coupon
 *     PUT    /coupons/{id}   replace every field but `used`: 200 with the coupon
 *     DELETE /coupons/{id}   204
 *
 * Each needs the booking capability; CouponAdminService checks it again.
 * valid_from and valid_to are ISO 8601 date-times (UTC on the way out).
 */
final class CouponRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];
    private const MAX_SERVICES = 200;

    /**
     * @param \Closure(): CouponAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(CouponAdminService::CAPABILITY));

        $this->router->add(
            '/coupons',
            'GET',
            fn (): array => \array_map(self::toJson(...), ($this->service)()->all()),
            $allowed
        );
        $this->router->add(
            '/coupons',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson(($this->service)()->save(self::fromRequest($request, 0))),
                201
            ),
            $allowed,
            self::fields()
        );
        $this->router->add(
            '/coupons/(?P<id>\d+)',
            'PUT',
            fn (\WP_REST_Request $request): array => self::toJson(
                ($this->service)()->save(self::fromRequest($request, self::id($request)))
            ),
            $allowed,
            self::ID + self::fields()
        );
        $this->router->add(
            '/coupons/(?P<id>\d+)',
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                ($this->service)()->delete(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fields(): array
    {
        return [
            'code' => ['type' => 'string', 'maxLength' => CouponAdminService::MAX_CODE_LENGTH, 'required' => true],
            'type' => [
                'type' => 'string',
                'enum' => \array_map(static fn (CouponType $t): string => $t->value, CouponType::cases()),
                'required' => true,
            ],
            'value' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
            'active' => ['type' => 'boolean', 'default' => true],
            'valid_from' => ['type' => ['string', 'null'], 'format' => 'date-time', 'default' => null],
            'valid_to' => ['type' => ['string', 'null'], 'format' => 'date-time', 'default' => null],
            'max_uses' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
            'service_ids' => [
                'type' => ['array', 'null'],
                'items' => ['type' => 'integer', 'minimum' => 1],
                'minItems' => 1,
                'maxItems' => self::MAX_SERVICES,
                'default' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'active' => $coupon->active,
            'valid_from' => self::iso($coupon->validFrom),
            'valid_to' => self::iso($coupon->validTo),
            'max_uses' => $coupon->maxUses,
            'used' => $coupon->used,
            'service_ids' => $coupon->serviceIds,
        ];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function fromRequest(\WP_REST_Request $request, int $id): Coupon
    {
        $p = $request->get_params();
        $services = $p['service_ids'] ?? null;

        return new Coupon(
            $id,
            \trim(self::string($p['code'] ?? null)),
            CouponType::from(self::string($p['type'] ?? null)),
            self::intValue($p['value'] ?? null),
            (bool) ($p['active'] ?? true),
            self::timestamp($p['valid_from'] ?? null),
            self::timestamp($p['valid_to'] ?? null),
            null === ($p['max_uses'] ?? null) ? null : self::intValue($p['max_uses']),
            0,
            \is_array($services) ? \array_values(\array_map(self::intValue(...), $services)) : null
        );
    }

    private static function iso(?int $timestamp): ?string
    {
        return null === $timestamp ? null : \gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * The schema has checked the format; a missing or null value is "no bound".
     */
    private static function timestamp(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        $parsed = \rest_parse_date(self::string($value));

        return false === $parsed ? throw self::wrongType() : $parsed;
    }

    /**
     * From the path only, as CustomerRoutes does: the body may carry an "id".
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        $id = $request->get_url_params()['id'] ?? null;

        return \is_numeric($id) ? (int) $id : throw new \LogicException('The route pattern makes the id numeric.');
    }

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
