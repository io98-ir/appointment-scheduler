<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Catalog\Application\CatalogService;
use Vaqtyar\Shared\Domain\Page;

/**
 * The five admin routes of one catalog resource:
 *
 *     GET    /{base}        a page, with X-WP-Total and X-WP-TotalPages
 *     POST   /{base}        create: 201 with the item
 *     GET    /{base}/{id}
 *     PUT    /{base}/{id}   replace every field: 200 with the item
 *     DELETE /{base}/{id}   soft delete: 204
 *
 * Each needs the catalog capability; CatalogService checks it again.
 */
final class CrudRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    public function __construct(private readonly Router $router)
    {
    }

    /**
     * @template T of object
     * @param non-falsy-string $base e.g. "/locations".
     * @param array<string, array<string, mixed>> $fields The item's args schema; PUT and POST share it.
     * @param \Closure(int, int): Page<T> $list Offset and limit.
     * @param \Closure(int): T $get
     * @param \Closure(Input, ?int): T $save The request's item, with the id for PUT, saved.
     * @param \Closure(int): void $delete
     * @param \Closure(T): array<string, mixed> $toJson
     */
    public function register(
        string $base,
        array $fields,
        \Closure $list,
        \Closure $get,
        \Closure $save,
        \Closure $delete,
        \Closure $toJson,
    ): void {
        $allowed = static fn (): bool => \current_user_can(Caps::name(CatalogService::CAPABILITY));
        $item = "{$base}/(?P<id>\\d+)";

        $this->router->add(
            $base,
            'GET',
            static function (\WP_REST_Request $request) use ($list, $toJson): \WP_REST_Response {
                $pagination = Pagination::fromRequest($request);
                $page = $list($pagination->offset(), $pagination->perPage);

                return $pagination->response(\array_map($toJson, $page->items), $page->total);
            },
            $allowed,
            Pagination::ARGS
        );
        $this->router->add(
            $base,
            'POST',
            static fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                $toJson($save(Input::fromRequest($request), null)),
                201
            ),
            $allowed,
            $fields
        );
        $this->router->add(
            $item,
            'GET',
            static fn (\WP_REST_Request $request): array => $toJson($get(self::id($request))),
            $allowed,
            self::ID
        );
        $this->router->add(
            $item,
            'PUT',
            static fn (\WP_REST_Request $request): array => $toJson(
                $save(Input::fromRequest($request), self::id($request))
            ),
            $allowed,
            self::ID + $fields
        );
        $this->router->add(
            $item,
            'DELETE',
            static function (\WP_REST_Request $request) use ($delete): \WP_REST_Response {
                $delete(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        // From the path only: get_param() prefers the JSON body, so an "id"
        // in the body of a PUT would pick the item instead of the URL.
        $id = $request->get_url_params()['id'] ?? null;

        return \is_numeric($id) ? (int) $id : throw new \LogicException('The route pattern makes the id numeric.');
    }
}
