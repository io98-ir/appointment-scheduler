<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\StartedAttempt;
use Vaqtyar\Modules\Payments\Application\WcOrderState;
use Vaqtyar\Modules\Payments\Application\WcOrders;

/**
 * WcOrders for gateway tests: orders are numbered from 41, and the state of
 * any order is whatever the test put in $states.
 */
final class FakeWcOrders implements WcOrders
{
    /** @var list<array{total: int, callback: string}> */
    public array $created = [];

    /** @var array<int|string, WcOrderState> PHP turns "41" into the key 41. */
    public array $states = [];

    public function __construct(private readonly string $currency)
    {
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function create(int $appointmentId, int $total, string $callbackUrl): StartedAttempt
    {
        $this->created[] = ['total' => $total, 'callback' => $callbackUrl];
        $id = (string) (40 + \count($this->created));

        return new StartedAttempt($id, 'https://shop.test/pay/' . $id);
    }

    public function find(string $orderId): ?WcOrderState
    {
        return $this->states[$orderId] ?? null;
    }
}
