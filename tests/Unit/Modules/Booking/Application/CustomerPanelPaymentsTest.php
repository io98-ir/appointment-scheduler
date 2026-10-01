<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\AppointmentDetail;
use Vaqtyar\Modules\Booking\Application\AppointmentQuery;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\AppointmentRow;
use Vaqtyar\Modules\Booking\Application\AppointmentService;
use Vaqtyar\Modules\Booking\Application\BookingJobs;
use Vaqtyar\Modules\Booking\Application\CustomerPanel;
use Vaqtyar\Modules\Booking\Application\PolicyReader;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Customers\Contracts\CustomerApi;
use Vaqtyar\Modules\Payments\Contracts\PaymentsApi;
use Vaqtyar\Modules\Payments\Contracts\PaymentTotals;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * A customer paying what is left of a deposit-paid appointment from the panel.
 */
final class CustomerPanelPaymentsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const NOW = 1_800_000_000;

    private PaymentStatus $paymentStatus = PaymentStatus::DepositPaid;

    private AppointmentStatus $status = AppointmentStatus::Confirmed;

    private int $start = self::NOW + 86_400;

    private int $customerId = 9;

    private int $paid = 300_000;

    private bool $gatewayUp = true;

    /** @var list<string> */
    private array $started = [];

    public function testTheCustomerPaysThePriceLessWhatWasPaid(): void
    {
        $url = $this->panel()->payRemainder('TOKEN', 12, 'https://site.test/my');

        self::assertSame('https://pay.test/12', $url);
        self::assertSame(['12 700000 https://site.test/my'], $this->started);
    }

    public function testSomeoneElsesAppointmentIsNotFound(): void
    {
        $this->customerId = 10;

        $this->expectException(NotFound::class);
        $this->panel()->payRemainder('TOKEN', 12, 'https://site.test/my');
    }

    public function testWithoutASessionNothingIsReached(): void
    {
        $this->expectException(Forbidden::class);
        $this->panel(null)->payRemainder(null, 12, 'https://site.test/my');
    }

    /**
     * @return iterable<string, array{PaymentStatus, AppointmentStatus, int}> money, status, start offset.
     */
    public static function nothingToPay(): iterable
    {
        yield 'already paid in full' => [PaymentStatus::Paid, AppointmentStatus::Confirmed, 86_400];
        yield 'nothing paid yet' => [PaymentStatus::Unpaid, AppointmentStatus::Confirmed, 86_400];
        yield 'partly refunded' => [PaymentStatus::PartiallyRefunded, AppointmentStatus::Confirmed, 86_400];
        yield 'cancelled' => [PaymentStatus::DepositPaid, AppointmentStatus::Cancelled, 86_400];
        yield 'already started' => [PaymentStatus::DepositPaid, AppointmentStatus::Confirmed, -60];
    }

    /**
     * @dataProvider nothingToPay
     */
    public function testNothingIsDueUnlessADepositWasPaidForAnAppointmentToCome(
        PaymentStatus $money,
        AppointmentStatus $status,
        int $startOffset
    ): void {
        $this->paymentStatus = $money;
        $this->status = $status;
        $this->start = self::NOW + $startOffset;

        try {
            $this->panel()->payRemainder('TOKEN', 12, 'https://site.test/my');
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('nothing_to_pay', $e->errorCode);
        }
        self::assertSame([], $this->started);
    }

    public function testAPaymentRecordedForTheWholePriceLeavesNothingToPay(): void
    {
        $this->paid = 1_000_000;

        $this->expectException(Conflict::class);
        $this->panel()->payRemainder('TOKEN', 12, 'https://site.test/my');
    }

    public function testAGatewayThatRefusesIsReportedAsPaymentUnavailable(): void
    {
        $this->gatewayUp = false;

        try {
            $this->panel()->payRemainder('TOKEN', 12, 'https://site.test/my');
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('payment_unavailable', $e->errorCode);
        }
    }

    public function testASiteWithoutPaymentsHasNothingToPay(): void
    {
        $this->expectException(Conflict::class);
        $this->panel(true, false)->payRemainder('TOKEN', 12, 'https://site.test/my');
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T&MockInterface
     */
    private static function mock(string $class): object
    {
        /** @var T&MockInterface $mock Mockery has no PHPStan extension here. */
        $mock = Mockery::mock($class);

        return $mock;
    }

    public function started(int $id, int $amount, string $returnUrl): void
    {
        $this->started[] = "{$id} {$amount} {$returnUrl}";
    }

    public function gatewayUp(): bool
    {
        return $this->gatewayUp;
    }

    public function paid(): int
    {
        return $this->paid;
    }

    private function panel(?bool $signedIn = true, bool $withPayments = true): CustomerPanel
    {
        $this->started = [];
        $test = $this;
        $row = new AppointmentRow(
            12,
            'AB12CD34',
            $this->status,
            $this->paymentStatus,
            9,
            1,
            7,
            5,
            3,
            $this->start,
            $this->start + 3600,
            'Asia/Tehran',
            1,
            Money::ofRial(1_000_000)
        );
        $query = self::mock(AppointmentQuery::class);
        $query->allows('detail')->andReturnUsing(
            static fn (int $id): ?AppointmentDetail => 12 === $id
                ? new AppointmentDetail(
                    $row,
                    'U',
                    'widget',
                    PriceQuote::empty(),
                    '',
                    '',
                    [],
                    [],
                    [],
                    null,
                    0,
                    null,
                    null
                )
                : null
        );
        $customers = new class ($this) implements CustomerApi {
            public function __construct(private readonly CustomerPanelPaymentsTest $test)
            {
            }

            public function canBook(int $customerId): bool
            {
                return true;
            }

            public function customerOfSession(?string $sessionToken): ?int
            {
                return null === $sessionToken ? null : $this->test->customerId();
            }

            public function forBooking(
                string $phone,
                string $firstName,
                string $lastName,
                ?string $email,
                ?string $sessionToken = null,
            ): int {
                return 9;
            }
        };
        $payments = new class ($test) implements PaymentsApi {
            public function __construct(private readonly CustomerPanelPaymentsTest $test)
            {
            }

            public function onlineAvailable(): bool
            {
                return true;
            }

            public function startOnline(int $appointmentId, Money $amount, string $returnUrl): string
            {
                if (!$this->test->gatewayUp()) {
                    throw new Conflict('no_gateway_available', 'down');
                }
                $this->test->started($appointmentId, $amount->amount, $returnUrl);

                return 'https://pay.test/' . $appointmentId;
            }

            public function totals(int $appointmentId): PaymentTotals
            {
                return new PaymentTotals($this->test->paid(), 0);
            }
        };
        $service = new AppointmentService(
            self::mock(AppointmentRepository::class),
            self::mock(PolicyReader::class),
            self::mock(SlotClaims::class),
            self::mock(ResourceLocker::class),
            self::mock(BookingJobs::class),
            self::mock(TransactionRunner::class),
            new FixedClock('@' . self::NOW),
            self::mock(Authorizer::class),
            static function (): void {
            }
        );

        return new CustomerPanel(
            $customers,
            $query,
            $service,
            new FixedClock('@' . self::NOW),
            $withPayments ? $payments : null
        );
    }

    public function customerId(): int
    {
        return $this->customerId;
    }
}
