<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Infrastructure;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;

/**
 * The search's WHERE clause; the SQL itself runs in the integration suite.
 */
final class CustomerSearchTest extends TestCase
{
    public function testAnEmptyQueryMatchesEveryone(): void
    {
        self::assertSame(['deleted_at IS NULL', []], WpdbCustomerRepository::where(''));
    }

    public function testTextMatchesTheNameAndTheEmail(): void
    {
        [$where, $args] = WpdbCustomerRepository::where('علی');

        self::assertStringNotContainsString('phone', $where);
        self::assertSame(['%علی%', '%علی%'], $args);
    }

    public function testANumberAlsoMatchesThePhoneWithoutItsLeadingZero(): void
    {
        [$where, $args] = WpdbCustomerRepository::where('0912 123');

        self::assertStringContainsString('phone LIKE %s', $where);
        self::assertSame('%912123%', $args[2]);
    }

    public function testLikeWildcardsInTheQueryAreLiteral(): void
    {
        self::assertSame('%50\%\_off%', WpdbCustomerRepository::where('50%_off')[1][0]);
    }
}
