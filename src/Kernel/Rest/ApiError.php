<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

/**
 * An error a REST controller answers with on purpose: the Router turns it
 * into the error envelope with this status (architecture §8).
 *
 * The message is shown to the client, so the controller passes it translated
 * and without any internal detail.
 */
final class ApiError extends \RuntimeException
{
    /**
     * @param string $errorCode Stable, machine-readable, e.g. "service_not_found".
     * @param array<string, mixed> $details Extra data for the client, e.g. field errors.
     * @param int|null $retryAfter Seconds, sent as the Retry-After header.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self(
            429,
            'rate_limited',
            \__('Too many requests. Please wait a moment and try again.', 'vaqtyar'),
            [],
            $retryAfter
        );
    }
}
