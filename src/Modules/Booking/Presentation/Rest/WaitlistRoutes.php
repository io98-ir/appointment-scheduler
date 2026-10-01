<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Booking\Application\WaitlistItem;
use Vaqtyar\Modules\Booking\Application\WaitlistService;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Shared\Domain\LocalDate;

/**
 * The waiting list API (docs/api.md):
 *
 *     POST   /waitlist        a guest asks to be told when a time opens on a full day: 201 with the id.
 *                             Public like POST /book: it needs the nonce, is rate limited per client,
 *                             and the phone must be verified (session_token) when the site requires it.
 *     GET    /waitlist        admin: the requests, newest first (page, per_page)
 *     DELETE /waitlist/{id}   admin: 204
 */
final class WaitlistRoutes
{
    private const JOIN_LIMIT = 10;
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    private const ARGS = [
        'variant' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'location' => ['type' => 'integer', 'minimum' => 1, 'required' => true],
        'staff' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
        'date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'required' => true],
        'phone' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 32, 'required' => true],
        'first_name' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
        'last_name' => ['type' => 'string', 'maxLength' => 100, 'default' => ''],
        'session_token' => ['type' => ['string', 'null'], 'maxLength' => 300, 'default' => null],
        'page_url' => ['type' => 'string', 'maxLength' => 2000, 'default' => ''],
    ];

    /**
     * @param \Closure(): WaitlistService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(WaitlistService::CAPABILITY));

        $this->router->add(
            '/waitlist',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => $this->join($request),
            Router::hasRestNonce(...),
            self::ARGS,
            new RateLimit(self::JOIN_LIMIT, 60)
        );
        $this->router->add(
            '/waitlist',
            'GET',
            function (\WP_REST_Request $request): \WP_REST_Response {
                $paging = Pagination::fromRequest($request);
                $page = ($this->service)()->page($paging->offset(), $paging->perPage);

                return $paging->response(\array_map(self::toJson(...), $page->items), $page->total);
            },
            $allowed,
            Pagination::ARGS
        );
        $this->router->add(
            '/waitlist/(?P<id>\d+)',
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                $id = $request->get_param('id');
                ($this->service)()->remove(\is_int($id) ? $id : 0);

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private function join(\WP_REST_Request $request): \WP_REST_Response
    {
        $staff = $request->get_param('staff');
        $session = $request->get_param('session_token');
        $id = ($this->service)()->join(
            self::string($request->get_param('phone')),
            \sanitize_text_field(self::string($request->get_param('first_name'))),
            \sanitize_text_field(self::string($request->get_param('last_name'))),
            \is_string($session) ? $session : null,
            new AvailabilityQuery(
                self::int($request->get_param('variant')),
                self::int($request->get_param('location')),
                \is_int($staff) ? $staff : null
            ),
            LocalDate::fromString(self::string($request->get_param('date'))),
            self::pageUrl($request->get_param('page_url'))
        );

        return new \WP_REST_Response(['id' => $id], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(WaitlistItem $item): array
    {
        $entry = $item->entry;

        return [
            'id' => $entry->id,
            'customer_id' => $entry->customerId,
            'customer_name' => $item->customer?->name,
            'customer_phone' => $item->customer?->phone,
            'variant_id' => $entry->variantId,
            'location_id' => $entry->locationId,
            'staff_id' => $entry->staffId,
            'date' => $entry->date->toString(),
            'status' => $entry->status->value,
            'notified_at' => null === $entry->notifiedAt ? null : \gmdate('c', $entry->notifiedAt),
            'created_at' => \gmdate('c', $entry->createdAt),
        ];
    }

    /**
     * The page the customer is sent back to: a page of this site that fits the table, else the home page.
     */
    private static function pageUrl(mixed $url): string
    {
        $home = \home_url('/');
        $page = \wp_validate_redirect(\is_string($url) ? $url : '', $home);

        return \strlen($page) > 500 || 1 === \preg_match('/[^\x21-\x7E]/', $page) ? $home : $page;
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }
}
