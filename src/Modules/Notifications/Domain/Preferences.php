<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * The owner's choices that shape sending, as the service reads them.
 *
 * @param string $adminEmail where the admin audience is written to; empty
 *     means nobody is.
 */
final class Preferences
{
    public function __construct(public readonly string $adminEmail, public readonly QuietHours $quietHours)
    {
    }
}
