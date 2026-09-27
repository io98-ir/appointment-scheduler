<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Customers\Application\CustomerService;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * The admin customer API:
 *
 *     GET    /customers?search=   a page, newest first, with X-WP-Total and X-WP-TotalPages
 *     POST   /customers           create: 201 with the customer
 *     GET    /customers/{id}
 *     PUT    /customers/{id}      replace every field: 200 with the customer
 *     DELETE /customers/{id}      soft delete: 204
 *
 * Each needs the customers capability; CustomerService checks it again.
 * The WordPress account is checked here, since only WordPress knows it.
 */
final class CustomerRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    public function __construct(private readonly Router $router, private readonly CustomerService $customers)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(CustomerService::CAPABILITY));
        $customers = $this->customers;

        $this->router->add(
            '/customers',
            'GET',
            static function (\WP_REST_Request $request) use ($customers): \WP_REST_Response {
                $pagination = Pagination::fromRequest($request);
                $search = $request->get_param('search');
                $page = $customers->customers(
                    \is_string($search) ? $search : '',
                    $pagination->offset(),
                    $pagination->perPage
                );

                return $pagination->response(\array_map(self::toJson(...), $page->items), $page->total);
            },
            $allowed,
            Pagination::ARGS + ['search' => ['type' => 'string', 'maxLength' => 100, 'default' => '']]
        );
        $this->router->add(
            '/customers',
            'POST',
            static fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson($customers->save(self::fromRequest($request, null))),
                201
            ),
            $allowed,
            self::fields()
        );
        $this->router->add(
            '/customers/(?P<id>\d+)',
            'GET',
            static fn (\WP_REST_Request $request): array => self::toJson($customers->customer(self::id($request))),
            $allowed,
            self::ID
        );
        $this->router->add(
            '/customers/(?P<id>\d+)',
            'PUT',
            static fn (\WP_REST_Request $request): array => self::toJson(
                $customers->save(self::fromRequest($request, self::id($request)))
            ),
            $allowed,
            self::ID + self::fields()
        );
        $this->router->add(
            '/customers/(?P<id>\d+)',
            'DELETE',
            static function (\WP_REST_Request $request) use ($customers): \WP_REST_Response {
                $customers->delete(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function toJson(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'uuid' => $customer->uuid?->toString(),
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'phone' => $customer->phone->e164,
            'email' => $customer->email?->value,
            'wp_user_id' => $customer->wpUserId,
            'birth_date' => $customer->birthDate?->toString(),
            'note' => $customer->note,
            'tags' => $customer->tags,
            'status' => $customer->status->value,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fields(): array
    {
        $optional = ['type' => ['string', 'null'], 'default' => null];

        return [
            'first_name' => ['type' => 'string', 'default' => ''],
            'last_name' => ['type' => 'string', 'default' => ''],
            'phone' => ['type' => 'string', 'required' => true],
            'email' => $optional,
            'wp_user_id' => ['type' => ['integer', 'null'], 'default' => null],
            'birth_date' => $optional,
            'note' => ['type' => 'string', 'default' => ''],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => []],
            'status' => [
                'type' => 'string',
                'enum' => \array_map(static fn (CustomerStatus $s): string => $s->value, CustomerStatus::cases()),
                'default' => CustomerStatus::Active->value,
            ],
        ];
    }

    /**
     * WordPress has checked the types against fields(). Names are trimmed
     * here; the entity refuses untrimmed ones. A form sends an empty
     * optional field as "", which reads as none.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function fromRequest(\WP_REST_Request $request, ?int $id): Customer
    {
        $p = $request->get_params();
        $email = self::optional($p['email'] ?? null);
        $birthDate = self::optional($p['birth_date'] ?? null);
        $wpUserId = $p['wp_user_id'] ?? null;
        if (null !== $wpUserId && (!\is_int($wpUserId) || $wpUserId < 1 || false === \get_userdata($wpUserId))) {
            throw new InvalidValue('unknown_user', 'The id does not name a WordPress user.');
        }
        $tags = $p['tags'] ?? [];

        return new Customer(
            $id,
            null,
            \trim(self::string($p['first_name'] ?? '')),
            \trim(self::string($p['last_name'] ?? '')),
            PhoneNumber::fromInput(self::string($p['phone'] ?? '')),
            null === $email ? null : Email::fromInput($email),
            $wpUserId,
            null === $birthDate ? null : LocalDate::fromString($birthDate),
            self::string($p['note'] ?? ''),
            \is_array($tags) ? \array_values(\array_map(self::string(...), $tags)) : [],
            CustomerStatus::from(self::string($p['status'] ?? CustomerStatus::Active->value)),
        );
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \LogicException('The schema let a wrong type through.');
    }

    private static function optional(mixed $value): ?string
    {
        return null === $value || '' === \trim(self::string($value)) ? null : self::string($value);
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        // From the path only: get_param() prefers the JSON body.
        $id = $request->get_url_params()['id'] ?? null;

        return \is_numeric($id) ? (int) $id : throw new \LogicException('The route pattern makes the id numeric.');
    }
}
