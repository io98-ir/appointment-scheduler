<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\Actor;
use Vaqtyar\Modules\Booking\Application\AppointmentRepository;
use Vaqtyar\Modules\Booking\Application\StoredAppointment;
use Vaqtyar\Modules\Booking\Application\UnpaidAppointments;
use Vaqtyar\Modules\Booking\Domain\Appointment\Appointment;
use Vaqtyar\Modules\Booking\Domain\Appointment\AppointmentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\PaymentStatus;
use Vaqtyar\Modules\Booking\Domain\Appointment\StatusChange;
use Vaqtyar\Modules\Booking\Domain\Appointment\TrackingCode;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceLine;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Shared\Domain\Ulid;

final class UnpaidAppointmentsTest extends TestCase
{
    public const NOW = 1_800_000_000;

    private ?StoredAppointment $stored = null;

    /** @var list<string> */
    private array $log = [];

    /** @var list<int> */
    private array $waiting = [];

    public function testExpiringFreesTheTimeAndTellsAvailability(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);

        self::assertTrue($this->service()->expire(12));

        self::assertSame(AppointmentStatus::Expired, $this->stored->appointment->status());
        self::assertSame(['find 12 for update', 'update expire by system', 'release 12', 'changed'], $this->log);
    }

    public function testAnAppointmentThatWasPaidMeanwhileDoesNotExpire(): void
    {
        $this->stored = self::stored(AppointmentStatus::Confirmed);

        self::assertFalse($this->service()->expire(12));
        self::assertSame(['find 12 for update'], $this->log);
    }

    public function testTheJobExpiresWhatHasWaitedTooLong(): void
    {
        $this->stored = self::stored(AppointmentStatus::PendingPayment);
        $this->waiting = [12];

        self::assertSame(1, $this->service()->expireOlderThan(1800, 50));
        self::assertContains('waiting before ' . (self::NOW - 1800), $this->log);
    }

    private static function stored(AppointmentStatus $status): StoredAppointment
    {
        $appointment = Appointment::restore(
            Ulid::fromParts(new \DateTimeImmutable('@1799000000'), \str_repeat("\1", 10)),
            TrackingCode::fromString('AB12CD34'),
            9,
            1,
            7,
            5,
            3,
            self::NOW + 86_400,
            self::NOW + 90_000,
            '2027-01-17',
            'Asia/Tehran',
            1,
            PriceQuote::empty()->with(new PriceLine(PriceLine::BASE, Money::ofRial(1_000_000))),
            '',
            PaymentStatus::Unpaid,
            $status
        );

        return new StoredAppointment(
            12,
            $appointment,
            ['staff:3'],
            self::NOW + 86_400,
            self::NOW + 90_000,
            [],
            0
        );
    }

    public function record(string $entry): void
    {
        $this->log[] = $entry;
    }

    public function current(): ?StoredAppointment
    {
        return $this->stored;
    }

    /**
     * @return list<int>
     */
    public function waiting(): array
    {
        return $this->waiting;
    }

    private function service(): UnpaidAppointments
    {
        $this->log = [];

        return new UnpaidAppointments(
            new class ($this) implements AppointmentRepository {
                public function __construct(private readonly UnpaidAppointmentsTest $test)
                {
                }

                public function add(
                    Appointment $appointment,
                    StatusChange $change,
                    string $source,
                    ?int $userId,
                    int $now,
                ): int {
                    throw new \LogicException('Not used.');
                }

                /**
                 * @param array<string, string> $answers
                 */
                public function saveAnswers(int $appointmentId, array $answers, int $now): void
                {
                    throw new \LogicException('Not used.');
                }

                public function find(int $id, bool $forUpdate = false): ?StoredAppointment
                {
                    $this->test->record("find {$id}" . ($forUpdate ? ' for update' : ''));

                    return 12 === $id ? $this->test->current() : null;
                }

                /**
                 * @param array<string, array{int|string|null, int|string|null}> $changes
                 */
                public function update(
                    int $id,
                    Appointment $appointment,
                    StatusChange $change,
                    Actor $actor,
                    ?string $reason,
                    array $changes,
                    int $now,
                ): void {
                    $this->test->record("update {$change->action} by {$actor->type}");
                }

                /**
                 * @return list<int>
                 */
                public function pendingPaymentBefore(int $cutoff, int $limit): array
                {
                    $this->test->record("waiting before {$cutoff}");

                    return $this->test->waiting();
                }

                public function release(int $id): void
                {
                    $this->test->record("release {$id}");
                }

                public function occupy(int $id, Claim $claim, int $variantId, int $partySize): void
                {
                    throw new \LogicException('Not used.');
                }

                public function saveNote(int $id, Appointment $appointment, string $note, Actor $actor, int $now): void
                {
                    throw new \LogicException('Not used.');
                }
            },
            new class implements TransactionRunner {
                public function run(callable $work): mixed
                {
                    return $work();
                }
            },
            new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('@' . UnpaidAppointmentsTest::NOW);
                }
            },
            function (): void {
                $this->log[] = 'changed';
            }
        );
    }
}
