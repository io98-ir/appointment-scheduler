<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Application;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Scheduling\Application\ScheduleService;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRuleRepository;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\NotFound;

final class ScheduleServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CatalogApi&MockInterface $catalog;

    private ScheduleRuleRepository&MockInterface $rules;

    private ScheduleExceptionRepository&MockInterface $exceptions;

    public bool $allowed = true;

    private ScheduleService $service;

    private Owner $staff;

    protected function setUp(): void
    {
        /** @var CatalogApi&MockInterface $catalog Mockery has no PHPStan extension here. */
        $catalog = Mockery::mock(CatalogApi::class);
        $this->catalog = $catalog;
        $this->catalog->allows('isStored')->andReturnUsing(
            static fn (string $kind, int $id): bool => 'staff' === $kind && 3 === $id
        );
        /** @var ScheduleRuleRepository&MockInterface $rules */
        $rules = Mockery::mock(ScheduleRuleRepository::class);
        $this->rules = $rules;
        /** @var ScheduleExceptionRepository&MockInterface $exceptions */
        $exceptions = Mockery::mock(ScheduleExceptionRepository::class);
        $this->exceptions = $exceptions;
        $authorizer = new class ($this) implements Authorizer {
            public function __construct(private readonly ScheduleServiceTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return ScheduleService::CAPABILITY === $capability && $this->test->allowed;
            }
        };
        $this->service = new ScheduleService($authorizer, $this->catalog, $this->rules, $this->exceptions);
        $this->staff = new Owner(OwnerType::Staff, 3);
    }

    public function testAUserWithoutTheCapabilityIsForbidden(): void
    {
        $this->allowed = false;

        $this->expectException(Forbidden::class);
        $this->service->weekly($this->staff);
    }

    public function testAnUnknownOwnerIsNotFound(): void
    {
        try {
            $this->service->weekly(new Owner(OwnerType::Resource, 9));
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('resource_not_found', $e->errorCode);
        }
    }

    public function testTheWeeklyScheduleIsReplacedAndReadBack(): void
    {
        $rules = [$this->rule(0, '09:00', '13:00'), $this->rule(0, '12:00', '12:30', RuleKind::Break)];
        $this->rules->expects('replace')->with(Mockery::on(fn (Owner $o): bool => $o->equals($this->staff)), $rules);
        $this->rules->expects('ofOwners')->andReturn($rules);

        self::assertSame($rules, $this->service->replaceWeekly($this->staff, $rules));
    }

    public function testOverlappingRangesOfOneKindOnOneDayAreRefused(): void
    {
        $this->rules->shouldNotReceive('replace');

        try {
            $this->service->replaceWeekly(
                $this->staff,
                [$this->rule(2, '09:00', '13:00'), $this->rule(2, '12:00', '18:00')]
            );
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('overlapping_rules', $e->errorCode);
        }
    }

    public function testRangesThatOnlyTouchAreFine(): void
    {
        $rules = [
            $this->rule(2, '13:00', '18:00'),
            $this->rule(2, '09:00', '13:00'),
            $this->rule(3, '09:00', '18:00'),
        ];
        $this->rules->expects('replace');
        $this->rules->expects('ofOwners')->andReturn($rules);

        self::assertCount(3, $this->service->replaceWeekly($this->staff, $rules));
    }

    public function testAnExceptionRangeIsBounded(): void
    {
        try {
            $this->service->exceptions($this->staff, self::date('2026-01-01'), self::date('2027-06-01'));
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_range', $e->errorCode);
        }
    }

    public function testExceptionsOfARangeAreRead(): void
    {
        $off = $this->exception(null);
        $this->exceptions->expects('between')->andReturn([$off]);

        self::assertSame(
            [$off],
            $this->service->exceptions($this->staff, self::date('2026-10-01'), self::date('2026-10-31'))
        );
    }

    public function testAnExceptionOfAnotherOwnerCannotBeMovedOrDeletedByAnUnknownId(): void
    {
        $this->exceptions->allows('find')->with(7)->andReturn(null);

        try {
            $this->service->saveException($this->exception(7));
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('exception_not_found', $e->errorCode);
        }
        $this->expectException(NotFound::class);
        $this->service->deleteException(7);
    }

    public function testAnExceptionIsSavedAndDeleted(): void
    {
        $saved = $this->exception(7);
        $this->exceptions->allows('find')->with(7)->andReturn($saved);
        $this->exceptions->expects('save')->andReturn($saved);
        $this->exceptions->expects('delete')->with(7);

        self::assertSame($saved, $this->service->saveException($this->exception(null)));
        $this->service->deleteException(7);
    }

    private function rule(int $weekday, string $start, string $end, RuleKind $kind = RuleKind::Work): ScheduleRule
    {
        return new ScheduleRule(
            null,
            $this->staff,
            $weekday,
            LocalTime::fromString($start),
            LocalTime::fromString($end),
            $kind
        );
    }

    private function exception(?int $id): ScheduleException
    {
        return new ScheduleException($id, $this->staff, self::date('2026-10-05'), null, null, ExceptionKind::Off, '');
    }

    private static function date(string $date): LocalDate
    {
        return LocalDate::fromString($date);
    }
}
