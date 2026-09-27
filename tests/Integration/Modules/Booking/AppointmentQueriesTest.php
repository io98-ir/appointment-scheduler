<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Booking\Application\AppointmentFilter;
use Vaqtyar\Modules\Booking\Application\AppointmentSort;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Infrastructure\Migrations\AddAppointmentStartIndex;
use Vaqtyar\Modules\Booking\Infrastructure\Query\WpdbAppointmentQuery;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * The admin reads over appointments (T2.8) through the real REST server:
 * the list's filters, order, pages and search, one appointment in full,
 * and the calendar. The rows are written straight to the tables, as the
 * reads never build the entity. The last test checks the plans (EXPLAIN)
 * on a couple of thousand rows.
 *
 * Tehran is UTC+03:30 all year; 00:30 on 2 October there is still 1 October
 * in UTC, which is what the local-date filter must get right.
 */
final class AppointmentQueriesTest extends TestCase
{
    use RealDatabase;

    private const TEHRAN = 'Asia/Tehran';

    private const OWN_TABLES = [
        'appointments',
        'appointment_extras',
        'appointment_history',
        'appointment_answers',
        'customers',
    ];

    private const UTC_FORMAT = 'Y-m-d H:i:s';

    /** @var list<int> */
    private array $users = [];

    /** @var array<string, int> by code */
    private array $ids = [];

    private int $ali;

    private int $sara;

    private int $gone;

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
        \wp_set_current_user(0);
        require_once \ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $user) {
            \wp_delete_user($user);
        }
        $this->emptyOwnTables();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testOnlyUsersWithTheBookingsCapabilityRead(): void
    {
        $this->fixture();
        $paths = ['/appointments', '/appointments/' . $this->ids['AAAA1111'], '/calendar'];
        $calendar = ['from' => '2026-10-01T00:00:00+03:30', 'to' => '2026-10-02T00:00:00+03:30'];

        $statuses = [];
        foreach (['', 'editor'] as $role) {
            if ('' !== $role) {
                $this->logInAs($role);
            }
            foreach ($paths as $path) {
                $statuses[] = $this->request($path, '/calendar' === $path ? $calendar : [])['status'];
            }
        }

        self::assertSame([401, 401, 401, 403, 403, 403], $statuses);
    }

    public function testTheListFiltersSortsAndPages(): void
    {
        $this->fixture();
        $this->logInAs('administrator');

        $all = $this->request('/appointments');
        self::assertSame(
            [200, ['DDDD4444', 'CCCC3333', 'BBBB2222', 'AAAA1111', 'EEEE5555'], '5'],
            [$all['status'], self::codes($all['body']), $all['headers']['X-WP-Total'] ?? null]
        );
        $page = $this->request('/appointments', ['per_page' => 2, 'page' => 2]);
        self::assertSame(
            [['BBBB2222', 'AAAA1111'], '3'],
            [self::codes($page['body']), $page['headers']['X-WP-TotalPages'] ?? null]
        );

        self::assertSame(
            [
                'created asc' => ['AAAA1111', 'BBBB2222', 'CCCC3333', 'DDDD4444', 'EEEE5555'],
                'start asc' => ['EEEE5555', 'AAAA1111', 'BBBB2222', 'CCCC3333', 'DDDD4444'],
                'status and staff' => ['CCCC3333', 'AAAA1111'],
                'service and location' => ['DDDD4444', 'BBBB2222'],
                'customer' => ['DDDD4444', 'BBBB2222'],
                '2 October in Tehran' => ['CCCC3333'],
                '1 October in Tehran' => ['BBBB2222', 'AAAA1111'],
                'from 1 October' => ['DDDD4444', 'CCCC3333', 'BBBB2222', 'AAAA1111'],
            ],
            [
                'created asc' => $this->codesOf(['orderby' => 'created', 'order' => 'asc']),
                'start asc' => $this->codesOf(['orderby' => 'start', 'order' => 'asc']),
                'status and staff' => $this->codesOf(['status' => ['confirmed', 'completed'], 'staff' => [1]]),
                'service and location' => $this->codesOf(['service' => 11, 'location' => 1]),
                'customer' => $this->codesOf(['customer' => $this->sara]),
                '2 October in Tehran' => $this->codesOf(['from' => '2026-10-02', 'to' => '2026-10-02']),
                '1 October in Tehran' => $this->codesOf(['from' => '2026-10-01', 'to' => '2026-10-01']),
                'from 1 October' => $this->codesOf(['from' => '2026-10-01']),
            ]
        );

        $first = $all['body'][0] ?? [];
        self::assertIsArray($first);
        self::assertSame(
            [
                'DDDD4444',
                'confirmed',
                'unpaid',
                ['id' => $this->sara, 'name' => 'سارا', 'phone' => '+989351112233', 'deleted' => false],
                2,
                '2026-10-05T09:00:00+03:30',
                '2026-10-05T10:00:00+03:30',
                ['amount' => 1_500_000, 'currency' => 'IRR'],
            ],
            self::pick($first, ['code', 'status', 'payment_status', 'customer', 'staff_id', 'start', 'end', 'total'])
        );
        $gone = $all['body'][4] ?? [];
        self::assertIsArray($gone);
        self::assertSame(
            ['id' => $this->gone, 'name' => 'Reza', 'phone' => null, 'deleted' => true],
            $gone['customer'] ?? null
        );
    }

    public function testTheSearchFindsATrackingCodeOrACustomer(): void
    {
        $this->fixture();
        $this->logInAs('administrator');

        self::assertSame(
            [
                'code' => ['CCCC3333'],
                'Arabic letters' => ['CCCC3333', 'AAAA1111'],
                'phone' => ['DDDD4444', 'BBBB2222'],
                'nobody' => [],
            ],
            [
                'code' => $this->codesOf(['search' => ' cccc3333 ']),
                'Arabic letters' => $this->codesOf(['search' => 'كريمي']),
                'phone' => $this->codesOf(['search' => '0935 111']),
                'nobody' => $this->codesOf(['search' => 'نیست']),
            ]
        );
        $nobody = $this->request('/appointments', ['search' => 'نیست']);
        self::assertSame('0', $nobody['headers']['X-WP-Total'] ?? null);
    }

    public function testBadFiltersAreRefused(): void
    {
        $this->logInAs('administrator');

        $refusals = [
            $this->request('/appointments', ['from' => '2026-10-02', 'to' => '2026-10-01']),
            $this->request('/appointments', ['from' => '2026-13-01']),
            $this->request('/appointments', ['status' => ['booked']]),
            $this->request('/calendar', ['from' => '2026-10-01T00:00:00+03:30', 'to' => '2026-11-12T00:00:01+03:30']),
            $this->request('/calendar', ['from' => '2026-10-02T00:00:00+03:30', 'to' => '2026-10-01T00:00:00+03:30']),
        ];

        self::assertSame(
            [
                [422, 'invalid_range'],
                [422, 'invalid_date'],
                [400, 'rest_invalid_param'],
                [422, 'range_too_long'],
                [422, 'invalid_range'],
            ],
            \array_map(static fn (array $r): array => [$r['status'], $r['body']['code'] ?? null], $refusals)
        );
    }

    public function testOneAppointmentComesWithItsPriceExtrasAnswersAndHistory(): void
    {
        $this->fixture();
        $this->logInAs('administrator');
        $id = $this->ids['AAAA1111'];
        $db = $this->realDb();
        $db->insert(Tables::name('appointment_extras'), [
            'appointment_id' => $id,
            'extra_id' => 4,
            'qty' => 2,
            'price' => 100_000,
            'created_at' => '2026-09-20 08:00:00',
            'updated_at' => '2026-09-20 08:00:00',
        ]);
        $db->insert(Tables::name('appointment_answers'), [
            'appointment_id' => $id,
            'field_key' => 'allergy',
            'value' => 'ندارد',
            'created_at' => '2026-09-20 08:00:00',
            'updated_at' => '2026-09-20 08:00:00',
        ]);
        foreach (
            [
                ['book', null, 'confirmed', null, null, '2026-09-20 08:00:00'],
                ['reschedule', 'confirmed', 'confirmed', '{"start_at":[1,2]}', 'تلفنی', '2026-09-21 08:00:00'],
            ] as [$action, $from, $to, $changes, $reason, $at]
        ) {
            $db->insert(Tables::name('appointment_history'), [
                'appointment_id' => $id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'changes' => $changes,
                'actor_type' => 'user',
                'actor_id' => 1,
                'reason' => $reason,
                'created_at' => $at,
            ]);
        }

        $detail = $this->request("/appointments/{$id}");
        $missing = $this->request('/appointments/999999');

        self::assertSame(200, $detail['status'], (string) \wp_json_encode($detail['body']));
        self::assertSame(
            [
                'AAAA1111',
                'Ali کریمی',
                'admin',
                'بدون عطر',
                [['extra_id' => 4, 'qty' => 2, 'unit_price' => ['amount' => 100_000, 'currency' => 'IRR']]],
                ['allergy' => 'ندارد'],
                '2026-09-20T11:30:00+03:30',
            ],
            [
                $detail['body']['code'] ?? null,
                \is_array($detail['body']['customer'] ?? null) ? $detail['body']['customer']['name'] : null,
                $detail['body']['source'] ?? null,
                $detail['body']['customer_note'] ?? null,
                $detail['body']['extras'] ?? null,
                (array) ($detail['body']['answers'] ?? []),
                $detail['body']['created_at'] ?? null,
            ]
        );
        $history = $detail['body']['history'] ?? [];
        self::assertIsArray($history);
        self::assertSame(
            [
                ['book', null, 'confirmed', [], null, '2026-09-20T11:30:00+03:30'],
                ['reschedule', 'confirmed', 'confirmed', ['start_at' => [1, 2]], 'تلفنی', '2026-09-21T11:30:00+03:30'],
            ],
            \array_map(
                static fn (mixed $change): array => \is_array($change)
                    ? self::pick($change, ['action', 'from', 'to', 'changes', 'reason', 'at'])
                    : [],
                $history
            )
        );
        self::assertSame([404, 'appointment_not_found'], [$missing['status'], $missing['body']['code'] ?? null]);
    }

    public function testTheCalendarShowsWhatTakesTimeInTheRange(): void
    {
        $this->fixture();
        $this->logInAs('administrator');

        self::assertSame(
            [
                '1 October' => ['AAAA1111'],
                '1 and 2 October' => ['AAAA1111', 'CCCC3333'],
                'staff 2, one week' => ['DDDD4444'],
                'started before the range' => ['AAAA1111'],
                'as JavaScript writes the times' => ['AAAA1111'],
            ],
            [
                '1 October' => $this->calendar('2026-10-01T00:00:00+03:30', '2026-10-02T00:00:00+03:30'),
                '1 and 2 October' => $this->calendar('2026-10-01T00:00:00+03:30', '2026-10-03T00:00:00+03:30'),
                'staff 2, one week' => $this->calendar('2026-10-01T00:00:00+03:30', '2026-10-08T00:00:00+03:30', [2]),
                'started before the range' => $this->calendar('2026-10-01T10:30:00+03:30', '2026-10-01T10:45:00+03:30'),
                'as JavaScript writes the times' => $this->calendar(
                    '2026-09-30T20:30:00.000Z',
                    '2026-10-01T20:30:00.000Z'
                ),
            ]
        );
    }

    public function testTheStartIndexExistsAndItsMigrationRunsAgain(): void
    {
        (new AddAppointmentStartIndex())->up($this->realDb());

        self::assertSame(
            'start_at',
            $this->realDb()->getVar(
                'SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                Tables::name('appointments'),
                'start_at'
            )
        );
    }

    /**
     * On 2,000 appointments over 400 days: each read the admin makes often
     * walks an index, and the list's order comes from the index, not a sort.
     */
    public function testTheReadsUseTheIndexes(): void
    {
        $this->seed(2_000);
        $query = new WpdbAppointmentQuery($this->realDb());
        $week = [LocalDate::fromString('2026-11-01'), LocalDate::fromString('2026-11-07')];
        $from = (new \DateTimeImmutable('2026-11-01T00:00:00+03:30'))->getTimestamp();

        $plans = [];
        $query->list(new AppointmentFilter(), null, AppointmentSort::StartDesc, 0, 20);
        $plans['newest first'] = $this->plan();
        $query->list(new AppointmentFilter(staffIds: [3]), null, AppointmentSort::StartDesc, 0, 20);
        $plans['one staff member'] = $this->plan();
        $query->list(new AppointmentFilter(customerId: 7), null, AppointmentSort::StartDesc, 0, 20);
        $plans['one customer'] = $this->plan();
        $query->list(new AppointmentFilter(from: $week[0], to: $week[1]), null, AppointmentSort::StartAsc, 0, 20);
        $plans['one week'] = $this->plan();
        $query->between($from, $from + 7 * 86_400, [], null, [AppointmentStatus::Confirmed], 2_001);
        $plans['calendar'] = $this->plan();
        $query->between($from, $from + 7 * 86_400, [3], null, [AppointmentStatus::Confirmed], 2_001);
        $plans['calendar, one staff member'] = $this->plan();

        $expected = [
            'newest first' => [['start_at'], false],
            'one staff member' => [['staff_start'], false],
            'one customer' => [['customer_start'], false],
            'one week' => [['start_at'], null],
            'calendar' => [['start_at', 'status_start'], null],
            'calendar, one staff member' => [['staff_start', 'start_at'], null],
        ];
        foreach ($expected as $name => [$keys, $sorts]) {
            [$key, $type, $filesort] = $plans[$name];
            self::assertContains($key, $keys, "{$name}: " . (string) \wp_json_encode($plans[$name]));
            self::assertNotSame('ALL', $type, $name);
            if (false === $sorts) {
                self::assertFalse($filesort, "{$name} sorts: " . (string) \wp_json_encode($plans[$name]));
            }
        }
    }

    /**
     * Five appointments in Tehran, created in code order.
     */
    private function fixture(): void
    {
        $customers = new WpdbCustomerRepository($this->realDb(), new SystemClock());
        $this->ali = (int) $customers->save(
            new Customer(null, null, 'Ali', 'کریمی', PhoneNumber::fromInput('09121234567'))
        )->id;
        $this->sara = (int) $customers->save(
            new Customer(null, null, 'سارا', '', PhoneNumber::fromInput('09351112233'))
        )->id;
        $this->gone = (int) $customers->save(
            new Customer(null, null, 'Reza', '', PhoneNumber::fromInput('09190000000'))
        )->id;
        $customers->delete($this->gone);

        foreach (
            [
                ['AAAA1111', $this->ali, 1, 10, 'confirmed', '2026-10-01 10:00'],
                ['BBBB2222', $this->sara, 2, 11, 'cancelled', '2026-10-01 12:00'],
                ['CCCC3333', $this->ali, 1, 10, 'completed', '2026-10-02 00:30'],
                ['DDDD4444', $this->sara, 2, 11, 'confirmed', '2026-10-05 09:00'],
                ['EEEE5555', $this->gone, 1, 10, 'no_show', '2026-09-28 09:00'],
            ] as $i => [$code, $customer, $staff, $service, $status, $local]
        ) {
            $start = new \DateTimeImmutable($local, new \DateTimeZone(self::TEHRAN));
            $this->ids[$code] = $this->realDb()->insert(
                Tables::name('appointments'),
                self::columns($i, $code, $customer, $staff, $service, $status, $start->getTimestamp()) + [
                    'customer_note' => 'بدون عطر',
                ]
            );
        }
    }

    /**
     * Rows spread over 400 days from 2026-06-01, eight staff members and a
     * hundred customers, in batches of one INSERT each.
     */
    private function seed(int $count): void
    {
        $db = $this->realDb();
        $statuses = ['confirmed', 'confirmed', 'confirmed', 'completed', 'cancelled'];
        $base = (new \DateTimeImmutable('2026-06-01T09:00:00+03:30'))->getTimestamp();
        for ($batch = 0; $batch < $count; $batch += 500) {
            $args = [];
            for ($i = $batch; $i < \min($count, $batch + 500); ++$i) {
                $start = $base + \intdiv($i, 5) * 86_400 + ($i % 5) * 3_600;
                $code = \sprintf('Z%07d', $i);
                $row = self::columns($i, $code, 1 + $i % 100, 1 + $i % 8, 10, $statuses[$i % 5], $start);
                $args = [...$args, ...\array_values($row)];
            }
            $db->execute(
                'INSERT INTO %i (uuid, code, customer_id, location_id, service_id, variant_id, staff_id, status,
                    payment_status, source, start_at, end_at, local_date, timezone, party_size, price_total,
                    price_lines, customer_note, internal_note, created_at, updated_at) VALUES '
                    . \implode(',', \array_fill(
                        0,
                        \intdiv(\count($args), 21),
                        '(%s, %s, %d, %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s)'
                    )),
                Tables::name('appointments'),
                ...$args
            );
        }
        $db->execute('ANALYZE TABLE %i', Tables::name('appointments'));
    }

    /**
     * An appointments row: one hour, in Tehran, at location 1. Service 11
     * costs 1,500,000 IRR and every other 1,000,000.
     *
     * @return array<string, int|string>
     */
    private static function columns(
        int $i,
        string $code,
        int $customer,
        int $staff,
        int $service,
        string $status,
        int $start,
    ): array {
        $zone = new \DateTimeZone(self::TEHRAN);

        return [
            'uuid' => \sprintf('01J%023d', $i),
            'code' => $code,
            'customer_id' => $customer,
            'location_id' => 1,
            'service_id' => $service,
            'variant_id' => $service * 10,
            'staff_id' => $staff,
            'status' => $status,
            'payment_status' => 'unpaid',
            'source' => 'admin',
            'start_at' => \gmdate(self::UTC_FORMAT, $start),
            'end_at' => \gmdate(self::UTC_FORMAT, $start + 3_600),
            'local_date' => (new \DateTimeImmutable('@' . $start))->setTimezone($zone)->format('Y-m-d'),
            'timezone' => self::TEHRAN,
            'party_size' => 1,
            'price_total' => 11 === $service ? 1_500_000 : 1_000_000,
            'price_lines' => '[]',
            'customer_note' => '',
            'internal_note' => '',
            'created_at' => \gmdate(self::UTC_FORMAT, (int) \strtotime('2026-09-20 08:00:00 UTC') + $i),
            'updated_at' => '2026-09-20 08:00:00',
        ];
    }

    /**
     * The plan of the last query on the appointments table.
     *
     * @return array{?string, ?string, bool} Its key, access type and whether it sorts.
     */
    private function plan(): array
    {
        $sql = $this->wpdb()->last_query;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the query Db prepared.
        $rows = $this->wpdb()->get_results('EXPLAIN ' . $sql, \ARRAY_A);
        $row = $rows[0] ?? [];
        $key = $row['key'] ?? null;
        $type = $row['type'] ?? null;
        $extra = $row['Extra'] ?? '';

        return [
            \is_string($key) ? $key : null,
            \is_string($type) ? $type : null,
            \is_string($extra) && \str_contains($extra, 'filesort'),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<string>
     */
    private function codesOf(array $params): array
    {
        $response = $this->request('/appointments', $params);
        self::assertSame(200, $response['status'], (string) \wp_json_encode($response['body']));

        return self::codes($response['body']);
    }

    /**
     * @param list<int> $staff
     * @return list<string>
     */
    private function calendar(string $from, string $to, array $staff = []): array
    {
        $params = ['from' => $from, 'to' => $to] + ([] === $staff ? [] : ['staff' => $staff]);
        $response = $this->request('/calendar', $params);
        self::assertSame(200, $response['status'], (string) \wp_json_encode($response['body']));

        return self::codes($response['body']);
    }

    /**
     * @param array<mixed> $items
     * @return list<string>
     */
    private static function codes(array $items): array
    {
        return \array_values(\array_map(
            static fn (mixed $item): string => \is_array($item) && \is_string($item['code'] ?? null)
                ? $item['code']
                : '',
            $items
        ));
    }

    /**
     * @param array<mixed> $item
     * @param list<string> $keys
     * @return list<mixed>
     */
    private static function pick(array $item, array $keys): array
    {
        return \array_map(static fn (string $key): mixed => $item[$key] ?? null, $keys);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'queries_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'queries_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, body: array<mixed>, headers: array<string, string>}
     */
    private function request(string $path, array $query = []): array
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . $path);
        $request->set_query_params($query);
        $response = \rest_do_request($request);
        $body = $response->get_data();
        /** @var array<string, string> $headers */
        $headers = $response->get_headers();

        return [
            'status' => $response->get_status(),
            'body' => \is_array($body) ? $body : [],
            'headers' => $headers,
        ];
    }

    private function emptyOwnTables(): void
    {
        foreach (self::OWN_TABLES as $table) {
            $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name($table));
        }
    }
}
