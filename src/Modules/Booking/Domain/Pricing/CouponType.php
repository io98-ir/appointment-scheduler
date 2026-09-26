<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Pricing;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
