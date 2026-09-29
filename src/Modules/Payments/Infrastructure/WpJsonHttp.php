<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Infrastructure;

use Vaqtyar\Modules\Payments\Application\GatewayException;
use Vaqtyar\Modules\Payments\Application\JsonHttp;

/**
 * JsonHttp over the WordPress HTTP API. The timeout is short: a customer is
 * waiting on the redirect, and a slow gateway is a reason to fail over.
 */
final class WpJsonHttp implements JsonHttp
{
    private const TIMEOUT_SECONDS = 12;

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function post(string $url, array $body): array
    {
        $response = \wp_remote_post($url, [
            'timeout' => self::TIMEOUT_SECONDS,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => (string) \wp_json_encode($body),
        ]);
        if ($response instanceof \WP_Error) {
            throw new GatewayException('HTTP error: ' . $response->get_error_code());
        }
        $status = (int) \wp_remote_retrieve_response_code($response);
        if ($status >= 500) {
            throw new GatewayException('Gateway answered ' . $status);
        }
        $decoded = \json_decode(\wp_remote_retrieve_body($response), true);
        if (!\is_array($decoded)) {
            throw new GatewayException('Gateway answered with something that is not JSON.');
        }

        return $decoded;
    }
}
