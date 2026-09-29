<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Booking;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * GET /reports/summary through the real REST server (T3.6): the figures
 * come from the local date of each appointment (Tehran here, whose midnight
 * is not UTC's), only confirmed and completed ones are booked, and the
 * range and location filters apply.
 */
final class ReportRestTest extends TestCase
{
    use RealDatabase;

    private const TEHRAN = 'Asia/Tehran';

    /** @var list<int> */
    private array $users = [];

    private int $serial = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('appointments'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core's global, reset as core's tests do.
        $GLOBALS['wp_rest_server'] = null;
    }

    protected function tearDown(): void
    {
        \wp_set_current_user(0);
        require_once \ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $id) {
            \wp_delete_user($id);
        }
        $this->realDb()->execute('TRUNCATE TABLE %i', Tables::name('appointments'));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- as in setUp().
        $GLOBALS['wp_rest_server'] = null;
        parent::tearDown();
    }

    public function testOnlyUsersWithTheBookingsCapabilityRead(): void
    {
        $params = ['from' => '2026-10-01', 'to' => '2026-10-03'];
        $statuses = [$this->request($params)['status']];
        $this->logInAs('editor');
        $statuses[] = $this->request($params)['status'];

        self::assertSame([401, 403], $statuses);
    }

    public function testTheFiguresFollowTheLocalDateAndTheBookedStatuses(): void
    {
        $this->fixture();
        $this->logInAs('administrator');

        $response = $this->request(['from' => '2026-10-01', 'to' => '2026-10-03']);
        $body = $response['body'];

        self::assertSame(200, $response['status'], (string) \wp_json_encode($body));
        self::assertSame(
            ['appointments' => 3, 'revenue' => 3_500_000, 'cancelled' => 1, 'no_show' => 1, 'cancel_rate' => 20.0],
            $body['totals'] ?? null
        );
        self::assertEquals(
            ['confirmed' => 2, 'completed' => 1, 'cancelled' => 1, 'no_show' => 1, 'pending_payment' => 1],
            (array) ($body['statuses'] ?? null)
        );
        self::assertSame(
            [
                ['date' => '2026-10-01', 'appointments' => 2, 'revenue' => 2_500_000],
                ['date' => '2026-10-02', 'appointments' => 1, 'revenue' => 1_000_000],
                ['date' => '2026-10-03', 'appointments' => 0, 'revenue' => 0],
            ],
            $body['days'] ?? null
        );
        self::assertSame(
            [
                ['id' => 10, 'appointments' => 2, 'revenue' => 2_000_000],
                ['id' => 11, 'appointments' => 1, 'revenue' => 1_500_000],
            ],
            $body['services'] ?? null
        );
        self::assertSame(
            [
                ['id' => 1, 'appointments' => 2, 'revenue' => 2_000_000],
                ['id' => 2, 'appointments' => 1, 'revenue' => 1_500_000],
            ],
            $body['staff'] ?? null
        );
    }

    public function testAnEarlyMorningStartCountsOnItsLocalDateNotItsUtcDate(): void
    {
        // 01:00 on the 2nd in Tehran is 21:30 on the 1st in UTC.
        $this->insert('confirmed', '2026-10-02 01:00:00', 1, 10, 1_000_000);
        $this->logInAs('administrator');

        $first = $this->request(['from' => '2026-10-01', 'to' => '2026-10-01'])['body'];
        $second = $this->request(['from' => '2026-10-02', 'to' => '2026-10-02'])['body'];

        self::assertSame([0, 1], [self::booked($first), self::booked($second)]);
    }

    public function testALocationFilterNarrowsTheFigures(): void
    {
        $this->fixture();
        $this->logInAs('administrator');

        $other = $this->request(['from' => '2026-10-01', 'to' => '2026-10-03', 'location' => 2])['body'];
        $same = $this->request(['from' => '2026-10-01', 'to' => '2026-10-03', 'location' => 1])['body'];

        self::assertSame([0, 3], [self::booked($other), self::booked($same)]);
    }

    /**
     * @param array<mixed> $body
     */
    private static function booked(array $body): mixed
    {
        $totals = $body['totals'] ?? [];

        return \is_array($totals) ? ($totals['appointments'] ?? null) : null;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badRanges(): iterable
    {
        yield 'reversed' => [['from' => '2026-10-03', 'to' => '2026-10-01']];
        yield 'over a year' => [['from' => '2025-01-01', 'to' => '2026-10-01']];
        yield 'not a date' => [['from' => '2026-13-45', 'to' => '2026-10-01']];
    }

    /**
     * @dataProvider badRanges
     * @param array<string, mixed> $params
     */
    public function testABadRangeIsRejected(array $params): void
    {
        $this->logInAs('administrator');

        self::assertSame(422, $this->request($params)['status']);
    }

    private function fixture(): void
    {
        $this->insert('confirmed', '2026-10-01 10:00:00', 1, 10, 1_000_000);
        $this->insert('completed', '2026-10-01 12:00:00', 2, 11, 1_500_000);
        $this->insert('confirmed', '2026-10-02 10:00:00', 1, 10, 1_000_000);
        $this->insert('cancelled', '2026-10-02 12:00:00', 2, 10, 1_000_000);
        $this->insert('no_show', '2026-10-03 10:00:00', 2, 10, 1_000_000);
        $this->insert('pending_payment', '2026-10-03 12:00:00', 2, 10, 1_000_000);
        $this->insert('confirmed', '2026-10-04 10:00:00', 1, 10, 1_000_000);
    }

    /**
     * @param string $local Tehran wall-clock start, Y-m-d H:i:s.
     */
    private function insert(string $status, string $local, int $staff, int $service, int $price): void
    {
        $zone = new \DateTimeZone(self::TEHRAN);
        $start = new \DateTimeImmutable($local, $zone);
        ++$this->serial;
        $this->realDb()->insert(Tables::name('appointments'), [
            'uuid' => \sprintf('01J%023d', $this->serial),
            'code' => \sprintf('R%07d', $this->serial),
            'customer_id' => 1,
            'location_id' => 1,
            'service_id' => $service,
            'variant_id' => $service * 10,
            'staff_id' => $staff,
            'status' => $status,
            'payment_status' => 'unpaid',
            'source' => 'admin',
            'start_at' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'end_at' => $start->modify('+1 hour')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'local_date' => $start->format('Y-m-d'),
            'timezone' => self::TEHRAN,
            'party_size' => 1,
            'price_total' => $price,
            'price_lines' => '[]',
            'customer_note' => '',
            'internal_note' => '',
            'created_at' => '2026-09-20 08:00:00',
            'updated_at' => '2026-09-20 08:00:00',
        ]);
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'report_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'report_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
    }

    /**
     * @param array<string, mixed> $params
     * @return array{status: int, body: array<mixed>}
     */
    private function request(array $params): array
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . '/reports/summary');
        $request->set_query_params($params);
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }
}
