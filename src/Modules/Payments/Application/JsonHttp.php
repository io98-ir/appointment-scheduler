<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

/**
 * The one outward call gateways make: a JSON POST. A Port so an adapter is
 * tested against recorded answers, not the network.
 */
interface JsonHttp
{
    /**
     * @param array<string, mixed> $body
     * @return array<mixed> the decoded JSON object.
     * @throws GatewayException on a network error, a non-JSON answer or a 5xx status.
     */
    public function post(string $url, array $body): array;
}
