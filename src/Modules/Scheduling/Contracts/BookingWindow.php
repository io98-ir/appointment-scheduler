<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Contracts;

/**
 * How soon and how far ahead one service can be booked, where it differs from the site's rules.
 */
final class BookingWindow
{
    /**
     * @param ?int $minNoticeMin booking closes this many minutes before the start; null keeps the site's.
     * @param ?int $maxAdvanceMin booking opens this many minutes ahead; null keeps the site's.
     */
    public function __construct(public readonly ?int $minNoticeMin, public readonly ?int $maxAdvanceMin)
    {
    }

    public static function site(): self
    {
        return new self(null, null);
    }
}
