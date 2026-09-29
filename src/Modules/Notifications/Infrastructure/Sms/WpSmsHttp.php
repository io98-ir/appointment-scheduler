<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;

/**
 * SmsHttp over the WordPress HTTP API. The messages of an error never hold
 * the URL: Kavenegar's carries the key.
 */
final class WpSmsHttp implements SmsHttp
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function post(string $url, array $headers, array $body, bool $json = true): array
    {
        $headers += ['Accept' => 'application/json'];
        if ($json) {
            $headers += ['Content-Type' => 'application/json'];
        }
        $response = \wp_remote_post($url, [
            'timeout' => self::TIMEOUT_SECONDS,
            'headers' => $headers,
            'body' => $json ? (string) \wp_json_encode($body) : $body,
        ]);
        if ($response instanceof \WP_Error) {
            throw new DeliveryFailed('HTTP error: ' . $response->get_error_code());
        }
        $status = (int) \wp_remote_retrieve_response_code($response);
        if ($status >= 500) {
            throw new DeliveryFailed('The provider answered ' . $status . '.');
        }
        $decoded = \json_decode(\wp_remote_retrieve_body($response), true);
        if (!\is_array($decoded)) {
            throw new DeliveryFailed('The provider answered with something that is not JSON (status ' . $status . ').');
        }

        return $decoded;
    }
}
