<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Infrastructure;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;

/**
 * The search's WHERE clause; the SQL itself runs in the integration suite.
 */
final class CustomerSearchTest extends TestCase
{
    public function testAnEmptyQueryMatchesEveryone(): void
    {
        self::assertSame(['deleted_at IS NULL', []], WpdbCustomerRepository::where('', null));
    }

    public function testTextMatchesTheNameAndTheEmail(): void
    {
        [$where, $args] = WpdbCustomerRepository::where('علی', null);

        self::assertStringNotContainsString('phone', $where);
        self::assertSame(['%علی%', '%علی%'], $args);
    }

    public function testANumberAlsoMatchesThePhoneWithoutItsLeadingZero(): void
    {
        [$where, $args] = WpdbCustomerRepository::where('0912 123', null);

        self::assertStringContainsString('phone LIKE %s', $where);
        self::assertSame('%912123%', $args[2]);
    }

    public function testLikeWildcardsInTheQueryAreLiteral(): void
    {
        self::assertSame('%50\%\_off%', WpdbCustomerRepository::where('50%_off', null)[1][0]);
    }

    public function testAStatusAddsAClauseAndItsOwnArgument(): void
    {
        [$where, $args] = WpdbCustomerRepository::where('', CustomerStatus::Blocked);

        self::assertSame(['deleted_at IS NULL AND status = %s', ['blocked']], [$where, $args]);
    }
}
