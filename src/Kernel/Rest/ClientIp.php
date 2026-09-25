<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Rest;

use Vaqtyar\Kernel\Hooks;

/**
 * The address a request came from, as far as rate limiting is concerned.
 */
final class ClientIp
{
    private function __construct(private readonly string $address)
    {
    }

    /**
     * REMOTE_ADDR only: forwarding headers are set by the client and would let
     * anyone pick their own bucket. A site behind a proxy or CDN sees the
     * proxy's address for every visitor, so it must use the filter.
     */
    public static function fromRequest(): self
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) && \is_string($_SERVER['REMOTE_ADDR'])
            ? \sanitize_text_field(\wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        /**
         * Filters the client address used for rate limiting. A site behind a
         * trusted proxy or CDN (e.g. ArvanCloud, Cloudflare) returns the
         * visitor's address from the header that proxy sets.
         *
         * @hook {hook_prefix}/rest/client_ip
         * @param string $remote REMOTE_ADDR of the request.
         */
        $address = \apply_filters(Hooks::name('rest/client_ip'), $remote);

        // Validated here as well: a broken filter (null, an array) must not turn
        // every limited route into a 500. It makes the client "unknown".
        $valid = \filter_var($address, \FILTER_VALIDATE_IP);

        return new self(false === $valid ? '' : $valid);
    }

    public static function fromString(string $address): self
    {
        return new self($address);
    }

    /**
     * IPv4 as is; IPv6 by its /64 network, since one host usually holds a
     * whole /64 and could rotate addresses around the limit. Every client
     * with no valid address shares the "unknown" bucket.
     */
    public function rateLimitKey(): string
    {
        $packed = false === \filter_var($this->address, \FILTER_VALIDATE_IP) ? false : \inet_pton($this->address);
        if (false === $packed) {
            return 'unknown';
        }
        if (4 === \strlen($packed)) {
            return (string) \inet_ntop($packed);
        }
        // An IPv4-mapped IPv6 address (::ffff:a.b.c.d) is the IPv4 client.
        if (\str_starts_with($packed, \str_repeat("\0", 10) . "\xff\xff")) {
            return (string) \inet_ntop(\substr($packed, 12));
        }

        return \inet_ntop(\substr($packed, 0, 8) . \str_repeat("\0", 8)) . '/64';
    }
}
