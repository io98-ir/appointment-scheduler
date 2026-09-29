<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

/**
 * The one outward call SMS providers make: a POST that answers with JSON. A
 * Port so an adapter is tested against recorded answers, not the network.
 */
interface SmsHttp
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body sent as a JSON object, or as a form when $json is false.
     * @return array<mixed> the decoded JSON object, whatever the status below 500.
     * @throws DeliveryFailed on a network error, a 5xx status or an answer that is not JSON.
     */
    public function post(string $url, array $headers, array $body, bool $json = true): array;
}
