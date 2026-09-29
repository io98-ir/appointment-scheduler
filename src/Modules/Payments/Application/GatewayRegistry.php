<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * The gateways a site has, in the order they are tried (failover). One of the
 * few registries the architecture allows (§13): gateways really are extended.
 */
final class GatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    /**
     * @param list<PaymentGateway> $gateways
     */
    public function __construct(array $gateways = [])
    {
        foreach ($gateways as $gateway) {
            $this->add($gateway);
        }
    }

    public function add(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->id()] = $gateway;
    }

    public function get(string $id): ?PaymentGateway
    {
        return $this->gateways[$id] ?? null;
    }

    /**
     * @return list<PaymentGateway> in registration order.
     */
    public function all(): array
    {
        return \array_values($this->gateways);
    }
}
