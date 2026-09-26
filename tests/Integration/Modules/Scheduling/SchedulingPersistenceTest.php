<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Scheduling\Domain\ExceptionKind;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleException;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\CreateSchedulingTables;
use Vaqtyar\Modules\Scheduling\Infrastructure\Migrations\ImportHolidays;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbHolidayRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleExceptionRepository;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The plugin booted with the Scheduling module, so its migrations have run.
 * TestCase with real commits: replacing a schedule opens its own transaction
 * (implementation-notes §5). The tables are emptied after each test.
 */
final class SchedulingPersistenceTest extends TestCase
{
    use RealDatabase;

    private const TABLES = ['schedule_rules', 'schedule_exceptions', 'holidays'];

    private WpdbScheduleRuleRepository $rules;

    private WpdbScheduleExceptionRepository $exceptions;

    private WpdbHolidayRepository $holidays;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->realDb();
        $clock = new FixedClock('2026-09-26 10:00:00');
        $this->rules = new WpdbScheduleRuleRepository($db, new Transaction($db), $clock);
        $this->exceptions = new WpdbScheduleExceptionRepository($db, $clock);
        $this->holidays = new WpdbHolidayRepository($db, $clock);
    }

    protected function tearDown(): void
    {
        foreach (self::TABLES as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
        parent::tearDown();
    }

    public function testBootCreatedTheTablesAsInnoDbAndRunningItAgainKeepsThem(): void
    {
        (new CreateSchedulingTables())->up($this->realDb());

        $engines = [];
        foreach (self::TABLES as $table) {
            $engines[$table] = $this->realDb()->getVar(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                Tables::name($table)
            );
        }
        self::assertSame(\array_fill_keys(self::TABLES, 'InnoDB'), $engines);
        self::assertSame('2', $this->realDb()->getVar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
            Tables::name('holidays'),
            'calendar_date'
        ));
    }

    public function testReplacesAWeeklyScheduleAsAWhole(): void
    {
        $ali = new Owner(OwnerType::Staff, 1);
        $room = new Owner(OwnerType::Resource, 1);
        $this->rules->replace($ali, [self::rule($ali, 0, '08:00', '12:00')]);
        $this->rules->replace($ali, [
            self::rule($ali, 6, '18:00', '24:00'),
            self::rule($ali, 0, '09:00', '17:00'),
            self::rule($ali, 0, '12:00', '13:00', RuleKind::Break),
        ]);
        $this->rules->replace($room, [self::rule($room, 1, '07:00', '22:00')]);
        $sara = new Owner(OwnerType::Staff, 2);
        $this->rules->replace($sara, [self::rule($sara, 0, '10:00', '11:00')]);

        self::assertSame(
            [
                'staff:1 0 09:00-17:00 work',
                'staff:1 0 12:00-13:00 break',
                'staff:1 6 18:00-24:00 work',
                'resource:1 1 07:00-22:00 work',
            ],
            \array_map(self::describeRule(...), $this->rules->ofOwners([$ali, $room]))
        );
        self::assertSame([], $this->rules->ofOwners([]));

        $this->rules->replace($ali, []);
        self::assertSame([], $this->rules->ofOwners([$ali]));
    }

    public function testRefusesRulesOfAnotherOwner(): void
    {
        $this->expectException(\LogicException::class);

        $sara = new Owner(OwnerType::Staff, 2);
        $this->rules->replace(new Owner(OwnerType::Staff, 1), [self::rule($sara, 0, '09:00', '17:00')]);
    }

    public function testSavesReadsAndDeletesExceptions(): void
    {
        $ali = new Owner(OwnerType::Staff, 1);
        $room = new Owner(OwnerType::Resource, 3);
        $off = $this->exceptions->save(self::exception($ali, '2026-10-05', null, null, ExceptionKind::Off, 'Sick'));
        $extra = $this->exceptions->save(self::exception($ali, '2026-10-04', '18:00', '24:00', ExceptionKind::Extra));
        $this->exceptions->save(self::exception($room, '2026-10-04', '10:00', '11:00', ExceptionKind::Blocked));
        $this->exceptions->save(self::exception($ali, '2026-10-20', null, null, ExceptionKind::Off));
        $sara = new Owner(OwnerType::Staff, 2);
        $this->exceptions->save(self::exception($sara, '2026-10-04', null, null, ExceptionKind::Off));

        self::assertNotNull($off->id);
        self::assertSame('Sick', $off->note);
        self::assertSame(
            [
                'resource:3 2026-10-04 10:00-11:00 blocked',
                'staff:1 2026-10-04 18:00-24:00 extra',
                'staff:1 2026-10-05 day off',
            ],
            \array_map(self::describeException(...), $this->exceptions->between(
                [$ali, $room],
                LocalDate::fromString('2026-10-04'),
                LocalDate::fromString('2026-10-05')
            ))
        );

        $moved = $this->exceptions->save(new ScheduleException(
            $extra->id,
            $ali,
            LocalDate::fromString('2026-10-06'),
            LocalTime::fromString('19:00'),
            LocalTime::fromString('21:00'),
            ExceptionKind::Extra,
            ''
        ));
        self::assertSame(
            [$extra->id, 'staff:1 2026-10-06 19:00-21:00 extra'],
            [$moved->id, self::describeException($moved)]
        );

        $this->exceptions->delete((int) $off->id);
        self::assertNull($this->exceptions->find((int) $off->id));
        self::assertSame(
            [],
            $this->exceptions->between([], LocalDate::fromString('2026-10-01'), LocalDate::fromString('2026-10-31'))
        );
    }

    public function testSavesReplacesAndDeletesHolidays(): void
    {
        $ir = Slug::fromInput('ir');
        $this->holidays->save(self::holiday('ir', '2026-10-10', 'First', HolidaySource::Manual));
        $this->holidays->save(self::holiday('ir', '2026-10-01', 'Second', HolidaySource::Manual));
        $this->holidays->save(self::holiday('ir', '2026-10-10', 'Renamed', HolidaySource::Manual));
        $this->holidays->save(self::holiday('other', '2026-10-05', 'Elsewhere', HolidaySource::Manual));

        self::assertSame(
            ['2026-10-01 Second manual', '2026-10-10 Renamed manual'],
            \array_map(self::describeHoliday(...), $this->holidays->between(
                $ir,
                LocalDate::fromString('2026-10-01'),
                LocalDate::fromString('2026-10-31')
            ))
        );

        $this->holidays->delete($ir, LocalDate::fromString('2026-10-10'));
        self::assertSame(
            ['2026-10-01 Second manual'],
            \array_map(self::describeHoliday(...), $this->holidays->between(
                $ir,
                LocalDate::fromString('2026-10-01'),
                LocalDate::fromString('2026-10-31')
            ))
        );
    }

    /**
     * The 1405 dataset, imported the way activation and updates do. A day
     * the admin already set is kept, and running it again adds nothing.
     */
    public function testImportsTheShippedDatasetOnceKeepingManualDays(): void
    {
        $this->holidays->save(self::holiday('ir', '2026-06-24', 'Corrected by the admin', HolidaySource::Manual));

        (new ImportHolidays(1405))->up($this->realDb());
        $afterFirst = $this->holidays->between(
            Slug::fromInput('ir'),
            LocalDate::fromString('2026-03-21'),
            LocalDate::fromString('2027-03-20')
        );
        $table = Tables::name('holidays');
        $before = $this->realDb()->getResults('SELECT * FROM %i ORDER BY id', $table);
        (new ImportHolidays(1405))->up($this->realDb());
        $after = $this->realDb()->getResults('SELECT * FROM %i ORDER BY id', $table);
        $addedAgain = $this->holidays->import([self::holiday('ir', '2026-03-21', 'Again', HolidaySource::Dataset)]);

        self::assertCount(26, $afterFirst);
        self::assertSame($before, $after, 'Running the migration again changes no row, not even a timestamp.');
        self::assertSame(0, $addedAgain);
        self::assertSame('2026-03-21', $afterFirst[0]->date->toString());
        self::assertSame(
            ['2026-03-23 عید نوروز dataset'],
            \array_map(self::describeHoliday(...), $this->holidays->between(
                Slug::fromInput('ir'),
                LocalDate::fromString('2026-03-23'),
                LocalDate::fromString('2026-03-23')
            ))
        );
        self::assertSame(
            ['2026-06-24 Corrected by the admin manual'],
            \array_map(self::describeHoliday(...), $this->holidays->between(
                Slug::fromInput('ir'),
                LocalDate::fromString('2026-06-24'),
                LocalDate::fromString('2026-06-24')
            ))
        );
    }

    private static function rule(
        Owner $owner,
        int $weekday,
        string $start,
        string $end,
        RuleKind $kind = RuleKind::Work,
    ): ScheduleRule {
        return new ScheduleRule(
            null,
            $owner,
            $weekday,
            LocalTime::fromString($start),
            LocalTime::fromString($end),
            $kind
        );
    }

    private static function exception(
        Owner $owner,
        string $date,
        ?string $start,
        ?string $end,
        ExceptionKind $kind,
        string $note = '',
    ): ScheduleException {
        return new ScheduleException(
            null,
            $owner,
            LocalDate::fromString($date),
            null === $start ? null : LocalTime::fromString($start),
            null === $end ? null : LocalTime::fromString($end),
            $kind,
            $note
        );
    }

    private static function holiday(string $calendar, string $date, string $title, HolidaySource $source): Holiday
    {
        return new Holiday(Slug::fromInput($calendar), LocalDate::fromString($date), Name::fromInput($title), $source);
    }

    private static function describeRule(ScheduleRule $rule): string
    {
        return \sprintf(
            '%s:%d %d %s-%s %s',
            $rule->owner->type->value,
            $rule->owner->id,
            $rule->weekday,
            $rule->start->toString(),
            $rule->end->toString(),
            $rule->kind->value
        );
    }

    private static function describeException(ScheduleException $exception): string
    {
        return \sprintf(
            '%s:%d %s %s %s',
            $exception->owner->type->value,
            $exception->owner->id,
            $exception->date->toString(),
            null === $exception->start || null === $exception->end
                ? 'day'
                : $exception->start->toString() . '-' . $exception->end->toString(),
            $exception->kind->value
        );
    }

    private static function describeHoliday(Holiday $holiday): string
    {
        return $holiday->date->toString() . ' ' . $holiday->title->value . ' ' . $holiday->source->value;
    }
}
