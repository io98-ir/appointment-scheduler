<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

trait AssertsInvalidValue
{
    private static function assertInvalid(string $code, callable $make): void
    {
        try {
            $make();
            self::fail('No exception for ' . $code);
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }
}
