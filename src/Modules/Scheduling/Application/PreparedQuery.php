<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Catalog\Contracts\LocationInfo;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Scheduling\Domain\Availability\SlotRequest;

/**
 * An AvailabilityQuery checked against the catalog (AvailabilityService):
 * the candidates at the location and the calculator's request.
 *
 * @internal
 */
final class PreparedQuery
{
    /**
     * @param list<StaffOffer> $staff at the location, or the chosen one.
     * @param list<array{int, list<ResourceUnit>}> $groups each needed quantity and its units at the location.
     * @param SlotRequest $slot without a booking window, which is applied after the cache.
     * @param int $longestMin the longest staff duration, which bounds the occupancies read.
     * @param string $cacheKey the prefix of each day's cache key.
     * @param int $minNoticeMin booking closes this many minutes before a start: the service's, or the site's.
     * @param int $maxAdvanceMin booking opens this many minutes ahead: the service's, or the site's.
     */
    public function __construct(
        public readonly LocationInfo $location,
        public readonly array $staff,
        public readonly array $groups,
        public readonly SlotRequest $slot,
        public readonly int $longestMin,
        public readonly string $cacheKey,
        public readonly int $minNoticeMin,
        public readonly int $maxAdvanceMin,
    ) {
    }
}
