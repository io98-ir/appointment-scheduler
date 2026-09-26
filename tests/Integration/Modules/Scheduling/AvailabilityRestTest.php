<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbLocationRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * GET /availability through the real REST server, as a guest, on stored
 * catalog, schedules and occupancies. The REST path runs on the system
 * clock, so the fixture is dated from today in Tehran. Staff work every day
 * 09:00-17:00; the variant takes 60 minutes on the default 30-minute grid.
 */
final class AvailabilityRestTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    private const TEHRAN = 'Asia/Tehran';

    private int $variant;

    private int $location;

    /** @var list<int> */
    private array $staff = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyOwnTables();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core's global, reset as core's tests do.
        $GLOBALS['wp_rest_server'] = null;
        \wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        $this->emptyCatalogTables();
        $this->emptyOwnTables();
        // A cached day must not outlive the rows it was computed from.
        \do_action(Hooks::name('scheduling/changed'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testAGuestGetsTheFreeStartsOfADay(): void
    {
        $this->fixture(1);
        $day = self::inDays(2);
        $this->occupy($this->staff[0], $day, '09:00', '10:00');
        // An expired hold does not take the time.
        $this->occupy($this->staff[0], $day, '10:00', '11:00', '2000-01-01 00:00:00');

        $response = $this->get(['date' => $day->toString()]);

        self::assertSame(200, $response['status']);
        $body = $response['body'];
        self::assertSame([self::TEHRAN, $day->toString(), 'available'], [
            $body['timezone'] ?? null,
            $body['date'] ?? null,
            $body['status'] ?? null,
        ]);
        /** @var list<array{start: string, end: string, staff_ids: list<int>, seats_left: int}> $slots */
        $slots = $body['slots'] ?? [];
        // 10:00 to 16:00 every 30 minutes; 09:00 and 09:30 overlap the booking.
        self::assertCount(13, $slots);
        self::assertSame(
            [
                'start' => $day->toString() . 'T10:00:00+03:30',
                'end' => $day->toString() . 'T11:00:00+03:30',
                'staff_ids' => [$this->staff[0]],
                'seats_left' => 1,
            ],
            $slots[0]
        );
    }

    public function testTheMonthAndFirstViews(): void
    {
        $this->fixture(1);
        $from = self::inDays(2);
        // The first day is taken whole.
        $this->occupy($this->staff[0], $from, '09:00', '17:00');

        $month = $this->get(['view' => 'month', 'date' => $from->toString(), 'days' => 3]);
        $first = $this->get(['view' => 'first', 'date' => $from->toString(), 'days' => 5]);

        self::assertSame(
            [
                ['date' => $from->toString(), 'status' => 'full'],
                ['date' => $from->addDays(1)->toString(), 'status' => 'available'],
                ['date' => $from->addDays(2)->toString(), 'status' => 'available'],
            ],
            $month['body']['days'] ?? null
        );
        self::assertSame($from->addDays(1)->toString(), $first['body']['date'] ?? null);
        self::assertIsArray($first['body']['slots'] ?? null);
        self::assertCount(15, $first['body']['slots']);
    }

    public function testRefusals(): void
    {
        $this->fixture(1);

        $unknown = $this->get(['variant' => 999_999]);
        $badExtra = $this->get(['extras' => [999_999]]);
        $badDate = $this->get(['date' => 'tomorrow']);
        $tooMany = $this->get(['view' => 'month', 'days' => 63]);

        self::assertSame(
            [
                [404, 'variant_not_found'],
                [422, 'invalid_extra'],
                [400, 'rest_invalid_param'],
                [400, 'rest_invalid_param'],
            ],
            [
                [$unknown['status'], $unknown['body']['code'] ?? null],
                [$badExtra['status'], $badExtra['body']['code'] ?? null],
                [$badDate['status'], $badDate['body']['code'] ?? null],
                [$tooMany['status'], $tooMany['body']['code'] ?? null],
            ]
        );
    }

    public function testADayIsCachedUntilTheScheduleOrTheCatalogChanges(): void
    {
        $this->fixture(1);
        $day = self::inDays(2);
        $count = fn (): int => \count((array) ($this->get(['date' => $day->toString()])['body']['slots'] ?? []));

        $before = $count();
        // Written behind the cache's back: still the cached answer.
        $this->realDb()->execute(
            'DELETE FROM %i WHERE owner_type = %s',
            Tables::name('schedule_rules'),
            OwnerType::Staff->value
        );
        $cached = $count();
        // Through the repository, which tells the cache.
        $this->rules()->replace(new Owner(OwnerType::Staff, $this->staff[0]), self::everyDay(
            $this->staff[0],
            '09:00',
            '11:00'
        ));
        $rescheduled = $count();
        // The staff member paused behind the cache's back, then the catalog's action.
        $this->realDb()->execute(
            'UPDATE %i SET status = %s WHERE id = %d',
            Tables::name('staff'),
            Status::Inactive->value,
            $this->staff[0]
        );
        $stillCached = $count();
        \do_action(Hooks::name('catalog/changed'));
        $paused = $count();
        // Back through the catalog repository, which fires the action itself.
        $staff = new WpdbStaffRepository($this->realDb(), new SystemClock());
        $member = $staff->find($this->staff[0]);
        self::assertNotNull($member);
        $staff->save(new Staff(
            $member->id,
            $member->name,
            $member->color,
            locationId: $member->locationId,
            status: Status::Active
        ));
        $resumed = $count();

        self::assertSame(
            [15, 15, 3, 3, 0, 3],
            [$before, $cached, $rescheduled, $stillCached, $paused, $resumed]
        );
    }

    /**
     * The done-when of T1.5: p95 under 300 ms on a busy fixture (10 staff,
     * five bookings each every day), each request on a cold cache.
     */
    public function testNinetyFifthPercentileUnderThreeHundredMilliseconds(): void
    {
        $this->fixture(10);
        $from = self::inDays(1);
        $rows = [];
        for ($d = 0; $d < 31; ++$d) {
            foreach ($this->staff as $i => $staffId) {
                foreach (['09:00', '10:30', '12:00', '14:00', '15:30'] as $j => $start) {
                    if (0 === ($i + $j + $d) % 4) {
                        continue;
                    }
                    $rows[] = [$staffId, $from->addDays($d), $start];
                }
            }
        }
        foreach ($rows as [$staffId, $date, $start]) {
            $end = LocalTime::fromMinutes(LocalTime::fromString($start)->minutes + 60)->toString();
            $this->occupy($staffId, $date, $start, $end);
        }

        // Untimed: the first request builds the REST server and every route.
        $this->get(['date' => $from->toString()]);
        $timings = ['day' => [], 'month' => []];
        for ($run = 0; $run < 60; ++$run) {
            $view = 0 === $run % 2 ? 'day' : 'month';
            \do_action(Hooks::name('scheduling/changed'));
            $started = \hrtime(true);
            $response = $this->get(['view' => $view, 'date' => $from->addDays($run % 28)->toString(), 'days' => 31]);
            $timings[$view][] = (\hrtime(true) - $started) / 1e6;
            self::assertSame(200, $response['status']);
        }

        foreach ($timings as $view => $ms) {
            \sort($ms);
            $p95 = $ms[(int) \ceil(0.95 * \count($ms)) - 1];
            \fwrite(\STDERR, \sprintf("\navailability %s view p95: %.1f ms", $view, $p95));
            self::assertLessThan(300.0, $p95, "The {$view} view");
        }
    }

    private function fixture(int $staffCount): void
    {
        $clock = new SystemClock();
        $db = $this->realDb();
        $location = (new WpdbLocationRepository($db, $clock))->save(
            new Location(null, Name::fromInput('Main'), new \DateTimeZone(self::TEHRAN))
        );
        $this->location = (int) $location->id;
        $staff = new WpdbStaffRepository($db, $clock);
        $this->staff = [];
        for ($i = 1; $i <= $staffCount; ++$i) {
            $member = $staff->save(
                new Staff(null, Name::fromInput("Staff {$i}"), Color::fromInput('#112233'), locationId: $this->location)
            );
            $this->staff[] = (int) $member->id;
            $this->rules()->replace(
                new Owner(OwnerType::Staff, (int) $member->id),
                self::everyDay((int) $member->id, '09:00', '17:00')
            );
        }
        $service = (new WpdbServiceRepository($db, new Transaction($db), $clock))->save(new Service(
            null,
            Name::fromInput('Visit'),
            [new Variant(null, '', 60, Money::ofRial(1_000_000), true)],
            \array_map(static fn (int $id): ServiceStaff => new ServiceStaff($id), $this->staff)
        ));
        $this->variant = (int) $service->variants[0]->id;
    }

    private function rules(): WpdbScheduleRuleRepository
    {
        $db = $this->realDb();

        return new WpdbScheduleRuleRepository($db, new Transaction($db), new SystemClock());
    }

    /**
     * @return list<ScheduleRule>
     */
    private static function everyDay(int $staffId, string $start, string $end): array
    {
        $rules = [];
        for ($weekday = 0; $weekday < 7; ++$weekday) {
            $rules[] = new ScheduleRule(
                null,
                new Owner(OwnerType::Staff, $staffId),
                $weekday,
                LocalTime::fromString($start),
                LocalTime::fromString($end),
                RuleKind::Work
            );
        }

        return $rules;
    }

    private function occupy(int $staffId, LocalDate $date, string $start, string $end, ?string $expiresAt = null): void
    {
        $utc = static fn (string $time): string => (new \DateTimeImmutable(
            $date->toString() . ' ' . $time,
            new \DateTimeZone(self::TEHRAN)
        ))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->realDb()->insert(Tables::name('occupancies'), [
            'owner_type' => null === $expiresAt ? 'appointment' : 'hold',
            'owner_id' => 1,
            'lock_key' => 'staff:' . $staffId,
            'variant_id' => $this->variant,
            'staff_id' => $staffId,
            'start_at' => $utc($start),
            'end_at' => $utc($end),
            'seats' => 1,
            'expires_at' => $expiresAt,
        ]);
    }

    private static function inDays(int $days): LocalDate
    {
        return LocalDate::fromDateTime(new \DateTimeImmutable('now'), new \DateTimeZone(self::TEHRAN))->addDays($days);
    }

    /**
     * @param array<string, mixed> $params Over the fixture's variant and location.
     * @return array{status: int, body: array<string, mixed>}
     */
    private function get(array $params): array
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . '/availability');
        $request->set_query_params($params + ['variant' => $this->variant, 'location' => $this->location]);
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    private function emptyOwnTables(): void
    {
        foreach (['schedule_rules', 'schedule_exceptions', 'occupancies', 'rate_limits'] as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
    }
}
