<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\BookingService;
use Vaqtyar\Modules\Booking\Application\FieldReader;
use Vaqtyar\Modules\Booking\Domain\Field\Field;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * What the widget calls to finish a booking as a guest (docs/api.md):
 *
 *     GET  /nonce                  a fresh REST nonce, since a cached page's is stale
 *     GET  /service-fields         the custom fields a booking of a service asks for
 *     POST /book                   a hold and the customer's details become an appointment
 *
 * All are public. POST /book needs the nonce and is rate limited per client;
 * the phone must be verified (session_token from POST /otp/verify) when the
 * site requires it. The reply is only what the
 * customer needs: the tracking code, the time and the price.
 */
final class GuestBookingRoutes
{
    private const BOOK_LIMIT = 10;
    private const READ_LIMIT = 120;

    /** Same text limits as the customers table's name columns and notes. */
    private const ARGS = [
        'hold_token' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{43}$', 'required' => true],
        'first_name' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
        'last_name' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
        'phone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32, 'required' => true],
        'email' => ['type' => ['string', 'null'], 'maxLength' => 191, 'default' => null],
        'customer_note' => ['type' => 'string', 'maxLength' => 2000, 'default' => ''],
        'answers' => ['type' => 'object', 'additionalProperties' => true, 'default' => []],
        'session_token' => ['type' => ['string', 'null'], 'maxLength' => 300, 'default' => null],
    ];

    /**
     * @param \Closure(): BookingService $service Built when a request needs it.
     * @param \Closure(): FieldReader $fields Built when a request needs it.
     * @param \Closure(): CatalogApi $catalog Built when a request needs it.
     */
    public function __construct(
        private readonly Router $router,
        private readonly \Closure $service,
        private readonly \Closure $fields,
        private readonly \Closure $catalog,
    ) {
    }

    public function register(): void
    {
        $this->router->add(
            '/nonce',
            'GET',
            static fn (): array => ['nonce' => \wp_create_nonce('wp_rest')],
            Router::ANYONE,
            [],
            new RateLimit(self::READ_LIMIT, 60)
        );
        $this->router->add(
            '/service-fields',
            'GET',
            fn (\WP_REST_Request $request): array => $this->fields($request),
            Router::ANYONE,
            ['service' => ['type' => 'integer', 'minimum' => 1, 'required' => true]],
            new RateLimit(self::READ_LIMIT, 60)
        );
        $this->router->add(
            '/book',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->book($request),
            Router::hasRestNonce(...),
            self::ARGS,
            new RateLimit(self::BOOK_LIMIT, 60)
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     * @return list<array<string, mixed>>
     */
    private function fields(\WP_REST_Request $request): array
    {
        $service = $request->get_param('service');
        $serviceId = \is_int($service) || \is_numeric($service) ? (int) $service : 0;
        if (!($this->catalog)()->isStored('service', $serviceId)) {
            throw new NotFound('service_not_found', 'No service has this id.');
        }

        return \array_map(static fn (Field $field): array => [
            'field_key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'required' => $field->required,
            'options' => $field->options,
            'show_if' => null === $field->showIf
                ? null
                : ['field' => $field->showIf->fieldKey, 'equals' => $field->showIf->equals],
        ], ($this->fields)()->forService($serviceId));
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function book(\WP_REST_Request $request): \WP_REST_Response
    {
        $email = $request->get_param('email');
        $answers = $request->get_param('answers');
        $session = $request->get_param('session_token');
        $booked = ($this->service)()->confirmAsGuest(
            HoldToken::fromString(self::string($request->get_param('hold_token'))),
            self::string($request->get_param('phone')),
            \sanitize_text_field(self::string($request->get_param('first_name'))),
            \sanitize_text_field(self::string($request->get_param('last_name'))),
            \is_string($email) ? $email : null,
            \sanitize_textarea_field(self::string($request->get_param('customer_note'))),
            \is_array($answers) ? $answers : [],
            \is_string($session) ? $session : null
        );
        $full = AppointmentJson::of($booked->id, $booked->appointment);

        return new \WP_REST_Response([
            'code' => $full['code'],
            'status' => $full['status'],
            'start' => $full['start'],
            'end' => $full['end'],
            'price' => $full['price'],
        ], 201);
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
