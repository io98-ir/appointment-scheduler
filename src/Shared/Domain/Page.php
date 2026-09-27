<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * One page of a list and the size of the whole list, for the X-WP-Total
 * headers.
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
    ) {
    }
}
