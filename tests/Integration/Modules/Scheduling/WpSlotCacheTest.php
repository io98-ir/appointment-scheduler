<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use Vaqtyar\Modules\Scheduling\Infrastructure\WpSlotCache;

/**
 * On the object cache of the test site (in-memory, as on most hosts).
 */
final class WpSlotCacheTest extends \WP_UnitTestCase
{
    public function testAnEntryIsReadBackUntilInvalidated(): void
    {
        $cache = new WpSlotCache();
        $cache->set('a', [true, [1, 2]]);
        $before = $cache->get('a');

        $cache->invalidate();

        self::assertSame([[true, [1, 2]], null, null], [$before, $cache->get('a'), $cache->get('missing')]);
    }

    public function testADayComputedBeforeAnotherRequestInvalidatedIsNotServedAfterIt(): void
    {
        $slow = new WpSlotCache();
        // The slow request misses, then reads the old rows...
        self::assertNull($slow->get('day'));
        // ...while another request changes a schedule...
        (new WpSlotCache())->invalidate();
        // ...and stores what it computed from them.
        $slow->set('day', ['stale']);

        self::assertNull((new WpSlotCache())->get('day'));
    }

    public function testAnEvictedGenerationStartsAfreshAndStillHidesOlderEntries(): void
    {
        $cache = new WpSlotCache();
        $cache->set('a', [1]);
        \wp_cache_flush();
        $cache->set('b', [2]);
        $cache->invalidate();

        self::assertSame([null, null], [$cache->get('a'), $cache->get('b')]);
    }
}
