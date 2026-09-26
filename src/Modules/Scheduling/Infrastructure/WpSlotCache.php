<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure;

use Vaqtyar\Kernel\Identity;
use Vaqtyar\Modules\Scheduling\Application\SlotCache;

/**
 * SlotCache on the WordPress object cache (architecture §11). Without a
 * persistent object cache it lasts one request, which a month view still
 * uses; with Redis or Memcached it is shared.
 *
 * invalidate() drops everything at once: each key carries a generation that
 * invalidate() moves on. A per-day scheme would need every writer to know
 * which days of which locations it touched, and a weekly schedule touches
 * all of them. Entries also expire after TTL seconds, a floor for anything
 * that changes without telling (a hand-edited row).
 */
final class WpSlotCache implements SlotCache
{
    public const TTL = 300;

    private const GENERATION = 'generation';

    /** Read once per request, so a day computed before an invalidation is not stored after it. */
    private ?string $generation = null;

    /**
     * @return ?array<mixed>
     */
    public function get(string $key): ?array
    {
        $found = false;
        $value = \wp_cache_get($this->generation() . ':' . $key, self::group(), false, $found);

        return $found && \is_array($value) ? $value : null;
    }

    /**
     * @param array<mixed> $value
     */
    public function set(string $key, array $value): void
    {
        \wp_cache_set($this->generation() . ':' . $key, $value, self::group(), self::TTL);
    }

    /**
     * Makes every stored entry unreachable.
     */
    public function invalidate(): void
    {
        if (false === \wp_cache_incr(self::GENERATION, 1, self::group())) {
            $this->start();
        }
        $this->generation = null;
    }

    /**
     * The generation this request read first. A day computed from rows read
     * before another request's invalidation is then stored under the old
     * generation, where nobody looks, rather than the new one.
     */
    private function generation(): string
    {
        if (null === $this->generation) {
            $generation = \wp_cache_get(self::GENERATION, self::group());
            $this->generation = \is_int($generation) || \is_string($generation)
                ? (string) $generation
                : $this->start();
        }

        return $this->generation;
    }

    /**
     * A missing generation (evicted, or never set) starts at a random value,
     * so entries of an earlier run of the counter are not read again.
     */
    private function start(): string
    {
        $generation = \random_int(1, 1 << 30);
        \wp_cache_set(self::GENERATION, $generation, self::group());

        return (string) $generation;
    }

    private static function group(): string
    {
        return Identity::PREFIX . '_availability';
    }
}
