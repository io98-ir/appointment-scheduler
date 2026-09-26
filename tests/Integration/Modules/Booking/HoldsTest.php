<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\HoldService;
use Vaqtyar\Modules\Booking\BookingModule;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Catalog\CatalogModule;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbLocationRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Modules\Scheduling\SchedulingModule;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * Holds on the real database: POST /holds as a guest, the conflict on a
 * taken start, extension and purge. One staff member works 09:00-17:00 in
 * Tehran every day; the variant takes 60 minutes. The parallel case is the
 * CI job "concurrency" (tests/Concurrency).
 */
final class HoldsTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    private const TEHRAN = 'Asia/Tehran';

    private const OWN_TABLES = [
        'schedule_rules',
        'occupancies',
        'holds',
        'resource_day_locks',
        'rate_limits',
        'price_rules',
        'coupons',
    ];

    private int $variant;

    private int $location;

    private int $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyOwnTables();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core's global, reset as core's tests do.
        $GLOBALS['wp_rest_server'] = null;
        \wp_set_current_user(0);
        $this->fixture();
    }

    protected function tearDown(): void
    {
        $this->emptyCatalogTables();
        $this->emptyOwnTables();
        \do_action(Hooks::name('scheduling/changed'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testAGuestHoldsAFreeStartAndItLeavesAvailability(): void
    {
        $day = self::inDays(2);
        $before = $this->starts($day);

        $response = $this->post($day, '10:00');

        self::assertSame(201, $response['status'], (string) \wp_json_encode($response['body']));
        $body = $response['body'];
        self::assertIsString($body['token'] ?? null);
        self::assertSame(
            [$this->staff, $day->toString() . 'T10:00:00+03:30', $day->toString() . 'T11:00:00+03:30'],
            [$body['staff_id'] ?? null, $body['start'] ?? null, $body['end'] ?? null]
        );
        $db = $this->realDb();
        $hash = HoldToken::fromString($body['token'])->hash();
        $holdId = $db->getVar('SELECT id FROM %i WHERE token_hash = %s', Tables::name('holds'), $hash);
        self::assertNotNull($holdId);
        self::assertSame(
            [['staff:' . $this->staff, 'hold']],
            \array_map(
                static fn (array $row): array => [$row['lock_key'], $row['owner_type']],
                $db->getResults(
                    'SELECT lock_key, owner_type FROM %i WHERE owner_id = %d',
                    Tables::name('occupancies'),
                    (int) $holdId
                )
            )
        );
        self::assertSame(
            '1',
            $db->getVar(
                'SELECT COUNT(*) FROM %i WHERE lock_key = %s',
                Tables::name('resource_day_locks'),
                'staff:' . $this->staff
            )
        );
        // The cached day was cleared: 10:00 is gone, and so is 09:30, which overlaps it.
        self::assertSame(
            \array_values(\array_diff($before, ['09:30', '10:00', '10:30'])),
            $this->starts($day)
        );
    }

    /**
     * A morning rule of the price_rules table prices the hold, and the quote
     * is kept with it (T2.3). A broken rule row is skipped, not fatal.
     */
    public function testTheHoldIsPricedWithTheTimeRulesAndKeepsTheQuote(): void
    {
        $db = $this->realDb();
        $rule = static fn (string $config, int $priority): int => $db->insert(Tables::name('price_rules'), [
            'type' => 'time',
            'service_id' => null,
            'config' => $config,
            'priority' => $priority,
            'status' => 'active',
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $rule('{"weekdays": [], "from": "25:00", "to": "12:00", "percent": 50}', 9);
        $morning = $rule('{"weekdays": [], "from": "09:00", "to": "12:00", "percent": 20}', 5);

        $response = $this->post(self::inDays(2), '10:00');

        $irr = static fn (int $amount): array => ['amount' => $amount, 'currency' => 'IRR'];
        $expected = [
            'total' => $irr(1_200_000),
            'lines' => [
                ['code' => 'base', 'amount' => $irr(1_000_000), 'ref' => null, 'qty' => 1],
                ['code' => 'time_rule', 'amount' => $irr(200_000), 'ref' => $morning, 'qty' => 1],
            ],
        ];
        self::assertSame(201, $response['status'], (string) \wp_json_encode($response['body']));
        self::assertSame($expected, $response['body']['price'] ?? null);
        $stored = $db->getVar('SELECT price_quote FROM %i', Tables::name('holds'));
        self::assertSame($expected, \json_decode((string) $stored, true));
    }

    /**
     * The code is found whatever its case, and its discount is a line of
     * the quote; an unknown code refuses the hold.
     */
    public function testACouponFromTheDatabaseDiscountsTheHold(): void
    {
        $coupon = $this->realDb()->insert(Tables::name('coupons'), [
            'code' => 'NOWRUZ',
            'type' => 'percent',
            'value' => 10,
            'status' => 'active',
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);

        $unknown = $this->post(self::inDays(2), '10:00', true, 'NOPE');
        $response = $this->post(self::inDays(2), '10:00', true, 'nowruz');

        self::assertSame([422, 'coupon_not_found'], [$unknown['status'], $unknown['body']['code'] ?? null]);
        self::assertSame(201, $response['status'], (string) \wp_json_encode($response['body']));
        /** @var array{total: array{amount: int}, lines: list<array{code: string, ref: ?int}>} $price */
        $price = $response['body']['price'] ?? [];
        self::assertSame(900_000, $price['total']['amount']);
        self::assertSame(['coupon', $coupon], [$price['lines'][1]['code'], $price['lines'][1]['ref']]);
    }

    public function testTheSameStartTwiceIsAConflict(): void
    {
        $day = self::inDays(2);
        $this->post($day, '10:00');

        $again = $this->post($day, '10:00');
        $overlapping = $this->post($day, '10:30');

        self::assertSame(
            [[409, 'slot_taken'], [409, 'slot_taken']],
            [
                [$again['status'], $again['body']['code'] ?? null],
                [$overlapping['status'], $overlapping['body']['code'] ?? null],
            ]
        );
        self::assertSame('1', $this->realDb()->getVar('SELECT COUNT(*) FROM %i', Tables::name('holds')));
    }

    public function testRefusals(): void
    {
        $day = self::inDays(2);

        $noNonce = $this->post($day, '10:00', false);
        $offGrid = $this->post($day, '10:10');
        $closed = $this->post($day, '20:00');

        self::assertSame(
            [[401, 'rest_forbidden'], [409, 'slot_taken'], [409, 'slot_taken']],
            [
                [$noNonce['status'], $noNonce['body']['code'] ?? null],
                [$offGrid['status'], $offGrid['body']['code'] ?? null],
                [$closed['status'], $closed['body']['code'] ?? null],
            ]
        );
    }

    public function testAHoldIsExtendedThenPurgedOnceExpired(): void
    {
        $service = $this->container()->get(HoldService::class);
        $start = (new \DateTimeImmutable(self::inDays(2)->toString() . ' 10:00', new \DateTimeZone(self::TEHRAN)))
            ->getTimestamp();
        $placed = $service->place(new AvailabilityQuery($this->variant, $this->location), $start);
        $db = $this->realDb();
        // Back-date it five minutes, as if the customer had been filling the form.
        $db->execute(
            'UPDATE %i SET created_at = created_at - INTERVAL 5 MINUTE, expires_at = expires_at - INTERVAL 5 MINUTE',
            Tables::name('holds')
        );

        $expiresAt = $service->extend(HoldToken::fromString($placed->token));

        $expected = \gmdate('Y-m-d H:i:s', $expiresAt);
        self::assertSame(
            [$expected, $expected],
            [
                $db->getVar('SELECT expires_at FROM %i', Tables::name('holds')),
                $db->getVar('SELECT expires_at FROM %i', Tables::name('occupancies')),
            ]
        );

        $db->execute('UPDATE %i SET expires_at = %s', Tables::name('holds'), '2000-01-01 00:00:00');
        \do_action(Hooks::name('booking/purge_holds'));

        self::assertSame(
            ['0', '0'],
            [
                $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('holds')),
                $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('occupancies')),
            ]
        );
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function post(LocalDate $day, string $time, bool $nonce = true, ?string $coupon = null): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . '/holds');
        $request->set_body_params([
            'variant' => $this->variant,
            'location' => $this->location,
            'start' => $day->toString() . 'T' . $time . ':00+03:30',
        ] + (null === $coupon ? [] : ['coupon' => $coupon]));
        if ($nonce) {
            $request->set_header('X-WP-Nonce', \wp_create_nonce('wp_rest'));
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * @return list<string> Tehran wall-clock starts of the day.
     */
    private function starts(LocalDate $day): array
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . '/availability');
        $request->set_query_params([
            'variant' => $this->variant,
            'location' => $this->location,
            'date' => $day->toString(),
        ]);
        $body = \rest_do_request($request)->get_data();
        /** @var list<array{start: string}> $slots */
        $slots = \is_array($body) && \is_array($body['slots'] ?? null) ? $body['slots'] : [];

        return \array_map(static fn (array $slot): string => \substr($slot['start'], 11, 5), $slots);
    }

    /**
     * The modules' services on the test database, as the plugin wires them.
     */
    private function container(): Container
    {
        $container = new Container();
        $db = $this->realDb();
        $container->singleton(Db::class, static fn (): Db => $db);
        $container->singleton(Transaction::class, static fn (): Transaction => new Transaction($db));
        $container->singleton(
            TransactionRunner::class,
            static fn (Container $c): TransactionRunner => $c->get(Transaction::class)
        );
        $container->singleton(Clock::class, static fn (): Clock => new SystemClock());
        $container->singleton(Settings::class, static fn (): Settings => new Settings());
        foreach ([new CatalogModule(), new SchedulingModule(), new BookingModule()] as $module) {
            $module->register($container);
        }

        return $container;
    }

    private function fixture(): void
    {
        $clock = new SystemClock();
        $db = $this->realDb();
        $this->location = (int) (new WpdbLocationRepository($db, $clock))->save(
            new Location(null, Name::fromInput('Main'), new \DateTimeZone(self::TEHRAN))
        )->id;
        $this->staff = (int) (new WpdbStaffRepository($db, $clock))->save(
            new Staff(null, Name::fromInput('Staff'), Color::fromInput('#112233'), locationId: $this->location)
        )->id;
        $rules = [];
        for ($weekday = 0; $weekday < 7; ++$weekday) {
            $rules[] = new ScheduleRule(
                null,
                new Owner(OwnerType::Staff, $this->staff),
                $weekday,
                LocalTime::fromString('09:00'),
                LocalTime::fromString('17:00'),
                RuleKind::Work
            );
        }
        (new WpdbScheduleRuleRepository($db, new Transaction($db), $clock))->replace(
            new Owner(OwnerType::Staff, $this->staff),
            $rules
        );
        $service = (new WpdbServiceRepository($db, new Transaction($db), $clock))->save(new Service(
            null,
            Name::fromInput('Visit'),
            [new Variant(null, '', 60, Money::ofRial(1_000_000), true)],
            [new ServiceStaff($this->staff)]
        ));
        $this->variant = (int) $service->variants[0]->id;
    }

    private static function inDays(int $days): LocalDate
    {
        return LocalDate::fromDateTime(new \DateTimeImmutable('now'), new \DateTimeZone(self::TEHRAN))->addDays($days);
    }

    private function emptyOwnTables(): void
    {
        foreach (self::OWN_TABLES as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
    }
}
