<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\KernelException;

/**
 * page and per_page for list routes, answered with the X-WP-Total and
 * X-WP-TotalPages headers as core does (architecture §9, principles §8).
 */
final class Pagination
{
    public const MAX_PER_PAGE = 100;

    /** Keeps the offset an int: a larger page would overflow to a float and fail as a 500. */
    public const MAX_PAGE = 1_000_000;

    /** Merge into the route's args; WordPress enforces the bounds (400 rest_invalid_param). */
    public const ARGS = [
        'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PAGE, 'default' => 1],
        'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE, 'default' => 20],
    ];

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request A request to a route with ARGS.
     */
    public static function fromRequest(\WP_REST_Request $request): self
    {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        // WordPress has already cast and bounded them; anything else means the
        // route was registered without ARGS.
        if (
            !\is_int($page) || $page < 1 || $page > self::MAX_PAGE
            || !\is_int($perPage) || $perPage < 1 || $perPage > self::MAX_PER_PAGE
        ) {
            throw KernelException::paginationArgsMissing();
        }

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @param list<mixed> $items This page's items.
     * @param int $total Items on all pages.
     */
    public function response(array $items, int $total): \WP_REST_Response
    {
        $response = new \WP_REST_Response($items);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) (int) \ceil($total / $this->perPage));

        return $response;
    }
}
