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
use Vaqtyar\Modules\Booking\Application\BookingService;
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
use Vaqtyar\Modules\Customers\Application\PhoneSessions;
use Vaqtyar\Modules\Customers\CustomersModule;
use Vaqtyar\Modules\Customers\Infrastructure\LoginSettings;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Modules\Customers\Infrastructure\Persistence\WpdbCustomerRepository;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Modules\Scheduling\SchedulingModule;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\SystemClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;
use Vaqtyar\Tests\Fixtures\FakeGateway;
use Vaqtyar\Tests\Integration\Modules\Catalog\CatalogTables;

/**
 * Holds on the real database: POST /holds as a guest, the conflict on a
 * taken start, extension and purge, and POST /bookings, which confirms a
 * hold as an appointment (T2.4), then reschedule, cancel and no-show under
 * the policies (T2.5). One staff member works 09:00-17:00 in
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
        'appointments',
        'appointment_extras',
        'appointment_history',
        'appointment_answers',
        'fields',
        'policies',
        'customers',
        'otp_codes',
        'payments',
        'refunds',
    ];

    /** @var list<int> */
    private array $users = [];

    private int $service;

    private int $variant;

    private int $location;

    private int $staff;

    private int $customer;

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
        \wp_set_current_user(0);
        require_once \ABSPATH . 'wp-admin/includes/user.php';
        foreach ($this->users as $user) {
            \wp_delete_user($user);
        }
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

    /**
     * Only a stored, active customer can be booked (T2.7); the hold stays.
     */
    public function testAnUnknownOrBlockedCustomerCannotBeBooked(): void
    {
        $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
        self::assertIsString($token);
        $blocked = (int) (new WpdbCustomerRepository($this->realDb(), new SystemClock()))->save(
            new Customer(null, null, 'Reza', '', PhoneNumber::fromInput('09122222222'), status: CustomerStatus::Blocked)
        )->id;
        $this->logInAs('administrator');

        foreach ([$this->customer + 1000, $blocked] as $customer) {
            $response = $this->book($token, [], $customer);
            self::assertSame([422, 'customer_unavailable'], [$response['status'], $response['body']['code'] ?? null]);
        }
        self::assertSame(201, $this->book($token)['status']);
    }

    /**
     * Staff confirm a hold: the appointment takes the hold's time and quote,
     * the occupancy is handed over for good, the coupon's use is counted and
     * the notification job is queued. The token works once.
     */
    public function testStaffConfirmAHoldAsAnAppointment(): void
    {
        $db = $this->realDb();
        $coupon = $db->insert(Tables::name('coupons'), [
            'code' => 'ONCE',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 1,
            'status' => 'active',
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $day = self::inDays(2);
        $hold = $this->post($day, '10:00', true, 'ONCE');
        $token = $hold['body']['token'] ?? null;
        self::assertIsString($token, (string) \wp_json_encode($hold['body']));
        $jobs = self::bookedJobs();

        $guest = $this->book($token);
        $this->logInAs('administrator');
        $booked = $this->book($token);
        $again = $this->book($token);

        self::assertSame(401, $guest['status']);
        self::assertSame(201, $booked['status'], (string) \wp_json_encode($booked['body']));
        $body = $booked['body'];
        /** @var array{total: array{amount: int}} $price */
        $price = $body['price'] ?? [];
        self::assertSame(
            ['confirmed', 'unpaid', $day->toString() . 'T10:00:00+03:30', 900_000],
            [
                $body['status'] ?? null,
                $body['payment_status'] ?? null,
                $body['start'] ?? null,
                $price['total']['amount'],
            ]
        );
        $code = $body['code'] ?? null;
        self::assertIsString($code);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{8}$/D', $code);
        self::assertSame([404, 'hold_not_found'], [$again['status'], $again['body']['code'] ?? null]);

        $id = $body['id'] ?? null;
        self::assertIsInt($id);
        self::assertSame(
            [[
                'customer_id' => (string) $this->customer,
                'staff_id' => (string) $this->staff,
                'status' => 'confirmed',
                'source' => 'admin',
                'price_total' => '900000',
                'local_date' => $day->toString(),
                'customer_note' => 'Aisle',
            ]],
            $db->getResults(
                'SELECT customer_id, staff_id, status, source, price_total, local_date, customer_note FROM %i',
                Tables::name('appointments')
            )
        );
        self::assertSame(
            [['owner_type' => 'appointment', 'owner_id' => (string) $id, 'expires_at' => null]],
            $db->getResults('SELECT owner_type, owner_id, expires_at FROM %i', Tables::name('occupancies'))
        );
        self::assertSame(
            ['0', '1', '1'],
            [
                $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('holds')),
                $db->getVar('SELECT used FROM %i WHERE id = %d', Tables::name('coupons'), $coupon),
                $db->getVar(
                    'SELECT COUNT(*) FROM %i WHERE appointment_id = %d AND action = %s AND to_status = %s',
                    Tables::name('appointment_history'),
                    $id,
                    'created',
                    'confirmed'
                ),
            ]
        );
        self::assertSame($jobs + 1, self::bookedJobs());
        // Still taken, now by the appointment, which never expires.
        self::assertNotContains('10:00', $this->starts($day));
    }

    /**
     * A global required field is enforced; a conditional field is only
     * required once its show_if matches; validated answers land in
     * appointment_answers (T2.6).
     */
    public function testCustomFieldAnswersAreValidatedAndStored(): void
    {
        $db = $this->realDb();
        $db->insert(Tables::name('fields'), [
            'scope' => 'global',
            'service_id' => null,
            'field_key' => 'has_car',
            'type' => 'checkbox',
            'label' => 'Has a car?',
            'required' => 0,
            'options' => null,
            'show_if' => null,
            'sort' => 0,
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $db->insert(Tables::name('fields'), [
            'scope' => 'global',
            'service_id' => null,
            'field_key' => 'plate',
            'type' => 'text',
            'label' => 'Plate number',
            'required' => 1,
            'options' => null,
            'show_if' => (string) \wp_json_encode(['field' => 'has_car', 'equals' => '1']),
            'sort' => 1,
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $day = self::inDays(2);
        $this->logInAs('administrator');

        // has_car is false: plate is hidden, so it is not required.
        $tokenA = $this->post($day, '10:00')['body']['token'] ?? null;
        self::assertIsString($tokenA);
        $hidden = $this->book($tokenA, ['has_car' => false]);
        self::assertSame(201, $hidden['status'], (string) \wp_json_encode($hidden['body']));

        // has_car is true this time: plate becomes required.
        $tokenB = $this->post($day, '11:00')['body']['token'] ?? null;
        self::assertIsString($tokenB);
        $missing = $this->book($tokenB, ['has_car' => true]);
        self::assertSame(
            [422, 'answer_required'],
            [$missing['status'], $missing['body']['code'] ?? null]
        );

        $booked = $this->book($tokenB, ['has_car' => true, 'plate' => '12A345']);
        self::assertSame(201, $booked['status'], (string) \wp_json_encode($booked['body']));

        self::assertSame(
            [
                ['field_key' => 'has_car', 'value' => '0'],
                ['field_key' => 'has_car', 'value' => '1'],
                ['field_key' => 'plate', 'value' => '12A345'],
            ],
            $db->getResults(
                'SELECT field_key, value FROM %i ORDER BY appointment_id, field_key',
                Tables::name('appointment_answers')
            )
        );
    }

    public function testAGuestBooksFromTheWidgetAndBecomesACustomer(): void
    {
        $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
        self::assertIsString($token);

        $booked = $this->guestBook($token, [
            'first_name' => 'Sara',
            'last_name' => 'Ahmadi',
            'phone' => '۰۹۳۵۱۱۱۲۲۳۳',
            'customer_note' => 'Window seat',
        ]);

        self::assertSame(201, $booked['status'], (string) \wp_json_encode($booked['body']));
        self::assertSame(8, \strlen(self::text($booked['body']['code'] ?? '')));
        self::assertSame('confirmed', $booked['body']['status'] ?? null);
        self::assertArrayNotHasKey('id', $booked['body']);
        $db = $this->realDb();
        self::assertSame(
            [['source' => 'widget', 'created_by' => null]],
            $db->getResults('SELECT source, created_by FROM %i', Tables::name('appointments'))
        );
        self::assertSame(
            [['phone' => '+989121234567'], ['phone' => '+989351112233']],
            $db->getResults('SELECT phone FROM %i ORDER BY id', Tables::name('customers'))
        );
    }

    public function testAGuestWithAKnownNumberIsThatCustomerAndCannotRenameThem(): void
    {
        $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
        self::assertIsString($token);

        $booked = $this->guestBook(
            $token,
            ['first_name' => 'Someone', 'last_name' => 'Else', 'phone' => '09121234567']
        );

        self::assertSame(201, $booked['status'], (string) \wp_json_encode($booked['body']));
        $db = $this->realDb();
        self::assertSame(
            [['customer_id' => (string) $this->customer]],
            $db->getResults('SELECT customer_id FROM %i', Tables::name('appointments'))
        );
        self::assertSame(
            [['first_name' => 'Ali']],
            $db->getResults('SELECT first_name FROM %i', Tables::name('customers'))
        );
    }

    public function testAGuestBookingNeedsTheNonceAndABlockedCustomerIsRefused(): void
    {
        $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
        self::assertIsString($token);

        $noNonce = $this->guestBook($token, ['first_name' => 'A', 'phone' => '09121234567'], false);
        self::assertContains($noNonce['status'], [401, 403]);

        $this->realDb()->execute(
            'UPDATE %i SET status = %s WHERE id = %d',
            Tables::name('customers'),
            'blocked',
            $this->customer
        );
        $blocked = $this->guestBook($token, ['first_name' => 'A', 'phone' => '09121234567']);
        self::assertSame(
            [422, 'customer_unavailable'],
            [$blocked['status'], $blocked['body']['code'] ?? null]
        );
        $bad = $this->guestBook($token, ['first_name' => 'A', 'phone' => 'not a phone']);
        self::assertSame([422, 'invalid_phone'], [$bad['status'], $bad['body']['code'] ?? null]);
    }

    public function testAWrongAnswerNamesItsFieldInTheErrorDetails(): void
    {
        $this->realDb()->insert(Tables::name('fields'), [
            'scope' => 'global',
            'service_id' => null,
            'field_key' => 'plate',
            'type' => 'text',
            'label' => 'Plate number',
            'required' => 1,
            'options' => null,
            'show_if' => null,
            'sort' => 0,
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
        self::assertIsString($token);

        $missing = $this->guestBook($token, ['first_name' => 'A', 'phone' => '09351112233']);

        self::assertSame(
            [422, 'answer_required', ['field_key' => 'plate']],
            [
                $missing['status'],
                $missing['body']['code'] ?? null,
                ((array) ($missing['body']['data'] ?? []))['details'] ?? null,
            ]
        );
    }

    public function testTheWidgetGetsAFreshNonceAndTheFieldsOfAService(): void
    {
        $this->realDb()->insert(Tables::name('fields'), [
            'scope' => 'global',
            'service_id' => null,
            'field_key' => 'plate',
            'type' => 'text',
            'label' => 'Plate number',
            'required' => 1,
            'options' => null,
            'show_if' => null,
            'sort' => 0,
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);

        $nonce = $this->guestGet('/nonce');
        $fields = $this->guestGet('/service-fields', ['service' => $this->service]);
        $unknown = $this->guestGet('/service-fields', ['service' => 999_999]);

        self::assertSame(200, $nonce['status']);
        self::assertNotFalse(\wp_verify_nonce(self::text($nonce['body']['nonce'] ?? ''), 'wp_rest'));
        self::assertSame(
            [200, ['plate'], [true]],
            [
                $fields['status'],
                \array_column($fields['body'], 'field_key'),
                \array_column($fields['body'], 'required'),
            ]
        );
        self::assertSame([404, 'service_not_found'], [$unknown['status'], $unknown['body']['code'] ?? null]);
    }

    public function testAVerifiedPhoneIsRequiredWhenTheSiteAsksForIt(): void
    {
        $settings = new Settings();
        $settings->save(new LoginSettings(true));
        $code = null;
        \add_action(
            Hooks::name('customers/otp'),
            static function (string $phone, string $sent) use (&$code): void {
                $code = $sent;
            },
            10,
            2
        );
        try {
            $token = $this->post(self::inDays(2), '10:00')['body']['token'] ?? null;
            self::assertIsString($token);
            $guest = ['first_name' => 'Sara', 'phone' => '09351112233'];

            $unverified = $this->guestBook($token, $guest);
            self::assertSame(
                [422, 'phone_not_verified'],
                [$unverified['status'], $unverified['body']['code'] ?? null]
            );

            $captcha = $this->guestGet('/captcha')['body'];
            $asked = $this->guestPost('/otp/request', [
                'phone' => '09351112233',
                'captcha_token' => self::text($captcha['token'] ?? ''),
                'captcha_answer' => (string) (self::number($captcha['a'] ?? 0) + self::number($captcha['b'] ?? 0)),
            ]);
            self::assertSame(202, $asked['status'], (string) \wp_json_encode($asked['body']));
            self::assertIsString($code);
            self::assertArrayNotHasKey('code', $asked['body']);

            $wrong = $this->guestPost('/otp/verify', [
                'phone' => '09351112233',
                'code' => '000000' === $code ? '111111' : '000000',
            ]);
            self::assertSame([422, 'invalid_code'], [$wrong['status'], $wrong['body']['code'] ?? null]);

            $verified = $this->guestPost('/otp/verify', ['phone' => '09351112233', 'code' => $code]);
            self::assertSame(200, $verified['status'], (string) \wp_json_encode($verified['body']));
            $session = self::text($verified['body']['token'] ?? '');

            $other = $this->guestBook($token, ['phone' => '09121234567'] + $guest + ['session_token' => $session]);
            self::assertSame(
                [422, 'phone_not_verified'],
                [$other['status'], $other['body']['code'] ?? null]
            );
            $booked = $this->guestBook($token, $guest + ['session_token' => $session]);
            self::assertSame(201, $booked['status'], (string) \wp_json_encode($booked['body']));
        } finally {
            $settings->save(new LoginSettings(false));
            \remove_all_actions(Hooks::name('customers/otp'));
        }
    }

    public function testACustomerSeesAndChangesOnlyTheirOwnAppointments(): void
    {
        $this->logInAs('administrator');
        $mine = $this->bookAt(self::inDays(3), '10:00');
        $other = (int) (new WpdbCustomerRepository($this->realDb(), new SystemClock()))->save(
            new Customer(null, null, 'Sara', 'Ahmadi', PhoneNumber::fromInput('09351112233'))
        )->id;
        $token = $this->post(self::inDays(3), '12:00')['body']['token'] ?? null;
        self::assertIsString($token);
        $theirs = $this->book($token, [], $other)['body']['id'] ?? null;
        self::assertIsInt($theirs);
        \wp_set_current_user(0);
        $session = (new PhoneSessions(\wp_salt('auth')))->issue('+989121234567', \time())['token'];

        $list = $this->panel('GET', '/my/appointments', [], $session);
        self::assertSame(200, $list['status'], (string) \wp_json_encode($list['body']));
        self::assertSame([$mine], \array_column($list['body'], 'id'));
        self::assertSame(
            [true, true],
            [
                self::map(self::map($list['body'][0] ?? null)['cancel'] ?? null)['allowed'] ?? null,
                self::map(self::map($list['body'][0] ?? null)['reschedule'] ?? null)['allowed'] ?? null,
            ]
        );

        self::assertContains($this->panel('GET', '/my/appointments', [], null)['status'], [401, 403]);
        self::assertContains($this->panel('GET', '/my/appointments', [], 'x.y')['status'], [401, 403]);
        $foreign = $this->panel('POST', "/my/appointments/{$theirs}/cancel", [], $session);
        self::assertSame([404, 'appointment_not_found'], [$foreign['status'], $foreign['body']['code'] ?? null]);

        $newStart = self::inDays(4)->toString() . 'T11:00:00+03:30';
        $moved = $this->panel('POST', "/my/appointments/{$mine}/reschedule", ['start' => $newStart], $session);
        self::assertSame(200, $moved['status'], (string) \wp_json_encode($moved['body']));
        self::assertSame($newStart, $moved['body']['start'] ?? null);

        $cancelled = $this->panel('POST', "/my/appointments/{$mine}/cancel", ['reason' => 'busy'], $session);
        self::assertSame([200, 'cancelled'], [$cancelled['status'], $cancelled['body']['status'] ?? null]);
        $after = $this->panel('GET', '/my/appointments', [], $session);
        $first = self::map($after['body'][0] ?? null);
        self::assertSame(
            [true, true, null, null],
            [
                \array_key_exists('cancel', $first),
                \array_key_exists('reschedule', $first),
                $first['cancel'] ?? null,
                $first['reschedule'] ?? null,
            ]
        );
    }

    public function testACodeIsNotSentWithoutTheCaptchaAnswer(): void
    {
        $sent = false;
        \add_action(Hooks::name('customers/otp'), static function () use (&$sent): void {
            $sent = true;
        });
        try {
            $captcha = $this->guestGet('/captcha')['body'];

            $refused = $this->guestPost('/otp/request', [
                'phone' => '09351112233',
                'captcha_token' => self::text($captcha['token'] ?? ''),
                'captcha_answer' => '99',
            ]);
            $config = $this->guestGet('/otp/config');

            self::assertSame([422, 'invalid_captcha'], [$refused['status'], $refused['body']['code'] ?? null]);
            self::assertFalse($sent);
            self::assertSame([200, false, 6], [
                $config['status'],
                $config['body']['required'] ?? null,
                $config['body']['code_length'] ?? null,
            ]);
        } finally {
            \remove_all_actions(Hooks::name('customers/otp'));
        }
    }

    /**
     * Moving frees the old time and takes the new one; cancelling frees it
     * all. A global policy refuses a late cancel unless staff override it
     * with a reason, which history keeps.
     */
    public function testStaffRescheduleAndCancelUnderThePolicies(): void
    {
        $db = $this->realDb();
        $day = self::inDays(2);
        $this->logInAs('administrator');
        $id = $this->bookAt($day, '10:00');

        $moved = $this->change($id, 'reschedule', ['start' => $day->toString() . 'T11:00:00+03:30']);

        self::assertSame(200, $moved['status'], (string) \wp_json_encode($moved['body']));
        self::assertSame($day->toString() . 'T11:00:00+03:30', $moved['body']['start'] ?? null);
        $starts = $this->starts($day);
        self::assertContains('10:00', $starts);
        self::assertNotContains('11:00', $starts);
        self::assertSame(
            '1',
            $db->getVar(
                'SELECT COUNT(*) FROM %i WHERE appointment_id = %d AND action = %s AND changes IS NOT NULL',
                Tables::name('appointment_history'),
                $id,
                'reschedule'
            )
        );

        $db->insert(Tables::name('policies'), [
            'type' => 'cancellation',
            'service_id' => 0,
            'config' => '{"notice_hours": 72, "refund": [{"hours": 72, "percent": 100}]}',
            'created_at' => '2026-09-27 10:00:00',
            'updated_at' => '2026-09-27 10:00:00',
        ]);
        $late = $this->change($id, 'cancel', ['reason' => 'Asked by phone']);
        $unexplained = $this->change($id, 'cancel', ['override' => true]);
        $overridden = $this->change($id, 'cancel', ['override' => true, 'reason' => 'Doctor is ill']);

        self::assertSame([409, 'policy.cancel_window_passed'], [$late['status'], $late['body']['code'] ?? null]);
        self::assertSame([422, 'reason_required'], [$unexplained['status'], $unexplained['body']['code'] ?? null]);
        self::assertSame(200, $overridden['status'], (string) \wp_json_encode($overridden['body']));
        $body = $overridden['body'];
        self::assertSame(['cancelled', true], [$body['status'] ?? null, $body['overridden'] ?? null]);
        self::assertSame('0', $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('occupancies')));
        self::assertSame(
            ['cancelled', 'Doctor is ill'],
            \array_values($db->getResults(
                'SELECT status, cancel_reason FROM %i WHERE id = %d',
                Tables::name('appointments'),
                $id
            )[0])
        );
        self::assertContains('11:00', $this->starts($day));

        $noShow = $this->change($this->bookAt($day, '12:00'), 'no-show');
        self::assertSame([409, 'not_started'], [$noShow['status'], $noShow['body']['code'] ?? null]);
    }

    public function testStaffCompleteApproveAndKeepAnInternalNote(): void
    {
        $this->logInAs('administrator');
        $db = $this->realDb();
        $id = $this->bookAt(self::inDays(2), '10:00');

        $early = $this->change($id, 'complete');
        $approve = $this->change($id, 'approve');
        $request = new \WP_REST_Request('PUT', '/' . Identity::REST_NAMESPACE . "/appointments/{$id}/note");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) \wp_json_encode(['note' => ' Prefers the morning ']));
        $note = \rest_do_request($request);

        self::assertSame([409, 'not_started'], [$early['status'], $early['body']['code'] ?? null]);
        self::assertSame([409, 'invalid_transition'], [$approve['status'], $approve['body']['code'] ?? null]);
        self::assertSame(204, $note->get_status());
        self::assertSame(
            'Prefers the morning',
            $db->getVar('SELECT internal_note FROM %i WHERE id = %d', Tables::name('appointments'), $id)
        );
        self::assertSame(
            '1',
            $db->getVar(
                'SELECT COUNT(*) FROM %i WHERE appointment_id = %d AND action = %s',
                Tables::name('appointment_history'),
                $id,
                'note'
            )
        );
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

    public function testAGuestPaysOnlineAndThePaymentConfirmsTheAppointment(): void
    {
        FakeGateway::$paid = true;
        self::assertTrue($this->guestGet('/payment-options')['body']['online'] ?? null);
        $jobs = self::bookedJobs();

        $booked = $this->bookOnline();

        self::assertSame('pending_payment', $booked['body']['status'] ?? null, (string) \wp_json_encode($booked));
        $url = self::text($booked['body']['payment_url'] ?? null);
        self::assertStringStartsWith('https://pay.test/F', $url);
        $db = $this->realDb();
        self::assertSame(
            ['pending_payment', 'unpaid', '1'],
            [
                $db->getVar('SELECT status FROM %i', Tables::name('appointments')),
                $db->getVar('SELECT payment_status FROM %i', Tables::name('appointments')),
                $this->appointmentOccupancies(),
            ],
            'The time is taken while the customer pays.'
        );
        self::assertSame($jobs, self::bookedJobs(), 'It is announced when paid, not when booked.');

        $authority = \basename($url);
        $back = $this->paymentCallback($authority);

        self::assertSame(302, $back->get_status());
        $location = self::text($back->get_headers()['Location'] ?? null);
        self::assertStringContainsString(Identity::PREFIX . '_payment=succeeded', $location);
        self::assertStringStartsWith(\home_url('/'), $location);
        self::assertSame(
            ['confirmed', 'paid'],
            [
                $db->getVar('SELECT status FROM %i', Tables::name('appointments')),
                $db->getVar('SELECT payment_status FROM %i', Tables::name('appointments')),
            ]
        );
        self::assertSame($jobs + 1, self::bookedJobs());

        $this->paymentCallback($authority);
        self::assertSame($jobs + 1, self::bookedJobs(), 'A repeated callback changes nothing.');
        self::assertSame('1', $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('payments')));
    }

    public function testStaffRecordARefundOnlyUpToWhatWasPaid(): void
    {
        FakeGateway::$paid = true;
        $this->paymentCallback(\basename(self::text($this->bookOnline()['body']['payment_url'] ?? null)));
        $paymentId = (int) $this->realDb()->getVar('SELECT id FROM %i', Tables::name('payments'));
        $this->logInAs('administrator');

        $first = $this->refund($paymentId, 600_000);
        $second = $this->refund($paymentId, 500_000);
        $rest = $this->refund($paymentId, 400_000);

        self::assertSame([201, 409, 201], [$first['status'], $second['status'], $rest['status']]);
        self::assertSame('refund_exceeds_payment', $second['body']['code'] ?? null);
        self::assertSame(
            '1000000',
            $this->realDb()->getVar('SELECT SUM(amount) FROM %i', Tables::name('refunds'))
        );
    }

    public function testAPaymentThatNeverCameBackIsSettledByTheReconciliationJob(): void
    {
        FakeGateway::$paid = true;
        $this->bookOnline();
        $db = $this->realDb();
        $db->execute('UPDATE %i SET created_at = created_at - INTERVAL 20 MINUTE', Tables::name('payments'));

        \do_action(Hooks::name('payments/reconcile'));

        self::assertSame('confirmed', $db->getVar('SELECT status FROM %i', Tables::name('appointments')));
        self::assertSame('succeeded', $db->getVar('SELECT status FROM %i', Tables::name('payments')));
    }

    public function testAnUnpaidAppointmentExpiresAndALatePaymentIsReportedNotApplied(): void
    {
        FakeGateway::$paid = true;
        $booked = $this->bookOnline();
        $authority = \basename(self::text($booked['body']['payment_url'] ?? null));
        $db = $this->realDb();
        $db->execute('UPDATE %i SET created_at = created_at - INTERVAL 31 MINUTE', Tables::name('appointments'));
        $attention = [];
        \add_action(
            Hooks::name('booking/needs_attention'),
            static function (int $appointmentId, int $paymentId) use (&$attention): void {
                $attention[] = [$appointmentId, $paymentId];
            },
            10,
            2
        );

        \do_action(Hooks::name('booking/expire_unpaid'));

        self::assertSame(
            ['expired', '0'],
            [
                $db->getVar('SELECT status FROM %i', Tables::name('appointments')),
                $this->appointmentOccupancies(),
            ]
        );

        $this->paymentCallback($authority);

        self::assertSame('expired', $db->getVar('SELECT status FROM %i', Tables::name('appointments')));
        self::assertSame('succeeded', $db->getVar('SELECT status FROM %i', Tables::name('payments')));
        self::assertCount(1, $attention, 'Staff are told: the money is taken.');
    }

    public function testWhenTheGatewayCannotTakeThePaymentNothingIsBooked(): void
    {
        FakeGateway::$paid = true;
        $token = $this->post(self::inDays(3), '12:00')['body']['token'] ?? null;
        self::assertIsString($token);
        $failing = new class () implements PaymentsApi {
            public function onlineAvailable(): bool
            {
                return true;
            }

            public function startOnline(int $appointmentId, Money $amount, string $returnUrl): string
            {
                throw new Conflict('no_gateway_available', 'down');
            }
        };
        $container = $this->container();
        $container->singleton(PaymentsApi::class, static fn (): PaymentsApi => $failing);
        $service = $container->get(BookingService::class);

        try {
            $service->confirmAsGuest(
                HoldToken::fromString($token),
                '09121234567',
                'Ali',
                'Karimi',
                null,
                '',
                [],
                null,
                \home_url('/')
            );
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('payment_unavailable', $e->errorCode);
        }

        $db = $this->realDb();
        self::assertSame(
            ['expired', '0'],
            [
                $db->getVar('SELECT status FROM %i', Tables::name('appointments')),
                $this->appointmentOccupancies(),
            ]
        );
    }

    /**
     * POST /book as a guest who pays online: the hold, then the booking.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function bookOnline(): array
    {
        $token = $this->post(self::inDays(3), '12:00')['body']['token'] ?? null;
        self::assertIsString($token);

        return $this->guestBook($token, [
            'first_name' => 'Sara',
            'phone' => '09351112233',
            'pay_online' => true,
            'return_url' => \home_url('/book'),
        ]);
    }

    private function appointmentOccupancies(): ?string
    {
        return $this->realDb()->getVar(
            'SELECT COUNT(*) FROM %i WHERE owner_type = %s',
            Tables::name('occupancies'),
            'appointment'
        );
    }

    private function paymentCallback(string $authority): \WP_REST_Response
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . '/payments/callback/fake');
        $request->set_query_params(['authority' => $authority, 'return' => \home_url('/book')]);

        return \rest_do_request($request);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function refund(int $paymentId, int $amount): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . '/payments/refunds');
        $request->set_body_params(['payment_id' => $paymentId, 'amount' => $amount, 'reason' => 'asked']);
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
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
     * @param array<string, mixed> $answers
     * @return array{status: int, body: array<string, mixed>}
     */
    private function book(string $token, array $answers = [], ?int $customer = null): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . '/bookings');
        $request->set_body_params([
            'hold_token' => $token,
            'customer_id' => $customer ?? $this->customer,
            'customer_note' => 'Aisle',
            'answers' => $answers,
        ]);
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * POST /book as a guest of the site, who has only the REST nonce.
     *
     * @param array<string, mixed> $params without the hold token.
     * @return array{status: int, body: array<string, mixed>}
     */
    private function guestBook(string $token, array $params, bool $nonce = true): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . '/book');
        $request->set_body_params(['hold_token' => $token] + $params);
        if ($nonce) {
            $request->set_header('X-WP-Nonce', \wp_create_nonce('wp_rest'));
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * A call of the customer panel: the REST nonce and, if given, the phone session.
     *
     * @param array<string, mixed> $params
     * @return array{status: int, body: array<mixed>}
     */
    private function panel(string $method, string $path, array $params, ?string $session): array
    {
        $request = new \WP_REST_Request($method, '/' . Identity::REST_NAMESPACE . $path);
        if ('GET' === $method) {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }
        $request->set_header('X-WP-Nonce', \wp_create_nonce('wp_rest'));
        if (null !== $session) {
            $request->set_header('X-Phone-Session', $session);
        }
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * A public POST with the REST nonce, as the widget sends it.
     *
     * @param array<string, mixed> $params
     * @return array{status: int, body: array<string, mixed>}
     */
    private function guestPost(string $path, array $params): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . $path);
        $request->set_body_params($params);
        $request->set_header('X-WP-Nonce', \wp_create_nonce('wp_rest'));
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, body: array<mixed>}
     */
    private function guestGet(string $path, array $query = []): array
    {
        $request = new \WP_REST_Request('GET', '/' . Identity::REST_NAMESPACE . $path);
        $request->set_query_params($query);
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    /**
     * A hold and its confirmation, as the logged-in staff member.
     */
    private function bookAt(LocalDate $day, string $time): int
    {
        $token = $this->post($day, $time)['body']['token'] ?? null;
        self::assertIsString($token);
        $id = $this->book($token)['body']['id'] ?? null;
        self::assertIsInt($id);

        return $id;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{status: int, body: array<string, mixed>}
     */
    private function change(int $id, string $action, array $params = []): array
    {
        $request = new \WP_REST_Request('POST', '/' . Identity::REST_NAMESPACE . "/appointments/{$id}/{$action}");
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) \wp_json_encode($params));
        $response = \rest_do_request($request);
        $body = $response->get_data();

        return ['status' => $response->get_status(), 'body' => \is_array($body) ? $body : []];
    }

    private static function bookedJobs(): int
    {
        $query = ['hook' => Hooks::name('booking/appointment_booked'), 'per_page' => -1];

        return \count(\as_get_scheduled_actions($query, 'ids'));
    }

    private function logInAs(string $role): void
    {
        $id = \wp_insert_user([
            'user_login' => 'booking_' . $role . '_' . \count($this->users),
            'user_pass' => \wp_generate_password(),
            'user_email' => 'booking_' . $role . \count($this->users) . '@example.com',
            'role' => $role,
        ]);
        self::assertIsInt($id);
        $this->users[] = $id;
        \wp_set_current_user($id);
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
        foreach ([new CatalogModule(), new SchedulingModule(), new CustomersModule(), new BookingModule()] as $module) {
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
        $this->service = (int) $service->id;
        $this->variant = (int) $service->variants[0]->id;
        $this->customer = (int) (new WpdbCustomerRepository($db, $clock))->save(
            new Customer(null, null, 'Ali', 'Karimi', PhoneNumber::fromInput('09121234567'))
        )->id;
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

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function number(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }
}
