<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Application;

use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Language;

/**
 * How the plugin shows itself: the calendar dates are written in, the digits
 * numbers use, and the language of its screens (GeneralSettings).
 */
final class Display
{
    public function __construct(
        public readonly Calendar $calendar,
        public readonly Digits $digits,
        public readonly Language $language,
    ) {
    }
}
