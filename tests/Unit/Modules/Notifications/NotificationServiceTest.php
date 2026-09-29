<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFacts;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\FactsPresenter;
use Vaqtyar\Modules\Notifications\Application\Message;
use Vaqtyar\Modules\Notifications\Application\NotificationChannel;
use Vaqtyar\Modules\Notifications\Application\NotificationLog;
use Vaqtyar\Modules\Notifications\Application\NotificationService;
use Vaqtyar\Modules\Notifications\Application\ReminderScheduler;
use Vaqtyar\Modules\Notifications\Application\TemplateRepository;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\Preferences;
use Vaqtyar\Modules\Notifications\Domain\QuietHours;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\TemplateRenderer;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Page;

final class NotificationServiceTest extends TestCase
{
    private const START = 1_790_000_000;

    private int $now = self::START - 3 * 86_400;

    private ?AppointmentFacts $facts = null;

    /** @var list<Template> */
    private array $templates = [];

    /** @var list<Message> */
    public array $sent = [];

    /** @var array<string, string> dedup key to status */
    public array $log = [];

    /** @var list<array{int, int, int, int}> */
    public array $scheduled = [];

    public bool $fail = false;

    private QuietHours $quiet;

    protected function setUp(): void
    {
        $this->facts = self::facts('confirmed', self::START);
        $this->quiet = new QuietHours(0, 0);
        $this->templates = [];
        $this->sent = [];
        $this->log = [];
        $this->scheduled = [];
        $this->fail = false;
        $this->now = self::START - 3 * 86_400;
    }

    public function testBookedSendsToEveryAudienceThatHasAnAddress(): void
    {
        $this->templates = [
            self::template(1, Trigger::Booked, Audience::Customer),
            self::template(2, Trigger::Booked, Audience::Staff),
            self::template(3, Trigger::Booked, Audience::Admin),
        ];

        $this->service()->appointmentChanged(Trigger::Booked, 7);

        self::assertSame(
            ['ali@example.com', 'sara@example.com', 'owner@example.com'],
            \array_map(static fn (Message $m): string => $m->recipient, $this->sent)
        );
        self::assertSame('Hello Ali Karimi, code AB12', $this->sent[0]->body);
    }

    public function testAnAudienceWithoutAnAddressIsSkipped(): void
    {
        $this->facts = self::facts('confirmed', self::START, customerEmail: null);
        $this->templates = [self::template(1, Trigger::Booked, Audience::Customer)];

        $this->service()->appointmentChanged(Trigger::Booked, 7);

        self::assertSame([], $this->sent);
        self::assertSame([], $this->log);
    }

    public function testTheSameEventTwiceSendsOnce(): void
    {
        $this->templates = [self::template(1, Trigger::Booked, Audience::Customer)];
        $service = $this->service();

        $service->appointmentChanged(Trigger::Booked, 7);
        $service->appointmentChanged(Trigger::Booked, 7);

        self::assertCount(1, $this->sent);
    }

    public function testAFailedSendIsLoggedAndTriedAgainByTheNextRun(): void
    {
        $this->templates = [self::template(1, Trigger::Booked, Audience::Customer)];
        $service = $this->service();
        $this->fail = true;

        $service->appointmentChanged(Trigger::Booked, 7);
        self::assertSame(['booked:7:1:' . self::START => 'failed'], $this->log);

        $this->fail = false;
        $service->appointmentChanged(Trigger::Booked, 7);
        self::assertSame(['booked:7:1:' . self::START => 'sent'], $this->log);
        self::assertCount(1, $this->sent);
    }

    public function testAMovedAppointmentIsAnotherMessage(): void
    {
        $this->templates = [self::template(1, Trigger::Rescheduled, Audience::Customer)];
        $service = $this->service();

        $service->appointmentChanged(Trigger::Rescheduled, 7);
        $this->facts = self::facts('confirmed', self::START + 3_600);
        $service->appointmentChanged(Trigger::Rescheduled, 7);

        self::assertCount(2, $this->sent);
    }

    public function testBookedIsNotSentForAnAppointmentThatAlreadyWasCancelled(): void
    {
        $this->facts = self::facts('cancelled', self::START);
        $this->templates = [
            self::template(1, Trigger::Booked, Audience::Customer),
            self::template(2, Trigger::Cancelled, Audience::Customer),
        ];

        $service = $this->service();
        $service->appointmentChanged(Trigger::Booked, 7);
        self::assertSame([], $this->sent);

        $service->appointmentChanged(Trigger::Cancelled, 7);
        self::assertCount(1, $this->sent);
    }

    public function testBookingSchedulesTheReminderOffsetBeforeTheStart(): void
    {
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->appointmentChanged(Trigger::Booked, 7);

        self::assertSame([[self::START - 86_400, 7, 4, self::START]], $this->scheduled);
    }

    public function testAReminderWhoseTimeHasPassedIsNotScheduled(): void
    {
        $this->now = self::START - 3_600;
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->appointmentChanged(Trigger::Booked, 7);

        self::assertSame([], $this->scheduled);
    }

    public function testACancelledAppointmentSchedulesNoReminder(): void
    {
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->appointmentChanged(Trigger::Cancelled, 7);

        self::assertSame([], $this->scheduled);
    }

    public function testAReminderIsSentWhenItsTimeComes(): void
    {
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->remind(7, 4, self::START);

        self::assertCount(1, $this->sent);
        self::assertSame(['reminder:7:4:' . self::START => 'sent'], $this->log);
    }

    public function testAReminderForAnAppointmentThatMovedOrIsNoLongerConfirmedIsDropped(): void
    {
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];
        $service = $this->service();

        $service->remind(7, 4, self::START - 60);
        $this->facts = self::facts('cancelled', self::START);
        $service->remind(7, 4, self::START);
        $this->facts = self::facts('pending_payment', self::START);
        $service->remind(7, 4, self::START);

        self::assertSame([], $this->sent);
    }

    public function testAReminderInQuietHoursWaitsForTheirEnd(): void
    {
        // 23:30 in Tehran (UTC+3:30), quiet from 22:00 to 08:00.
        $this->now = (new \DateTimeImmutable('2026-10-01 23:30', new \DateTimeZone('Asia/Tehran')))->getTimestamp();
        $start = (new \DateTimeImmutable('2026-10-02 14:00', new \DateTimeZone('Asia/Tehran')))->getTimestamp();
        $this->facts = self::facts('confirmed', $start);
        $this->quiet = new QuietHours(22 * 60, 8 * 60);
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->remind(7, 4, $start);

        self::assertSame([], $this->sent);
        $morning = (new \DateTimeImmutable('2026-10-02 08:00', new \DateTimeZone('Asia/Tehran')))->getTimestamp();
        self::assertSame([[$morning, 7, 4, $start]], $this->scheduled);
    }

    public function testAReminderThatQuietHoursWouldPushPastTheStartIsSentNow(): void
    {
        $this->now = (new \DateTimeImmutable('2026-10-01 23:30', new \DateTimeZone('Asia/Tehran')))->getTimestamp();
        $this->facts = self::facts('confirmed', $this->now + 3_600);
        $this->quiet = new QuietHours(22 * 60, 8 * 60);
        $this->templates = [self::template(4, Trigger::Reminder, Audience::Customer, 1440)];

        $this->service()->remind(7, 4, $this->now + 3_600);

        self::assertCount(1, $this->sent);
    }

    public function testADisabledOrUnknownTemplateSendsNothing(): void
    {
        $this->templates = [new Template(4, Trigger::Reminder, Audience::Customer, 'email', 60, 's', 'b', false)];
        $service = $this->service();

        $service->remind(7, 4, self::START);
        $service->remind(7, 99, self::START);

        self::assertSame([], $this->sent);
    }

    public function testAnUnregisteredChannelIsSkipped(): void
    {
        $this->templates = [new Template(1, Trigger::Booked, Audience::Customer, 'sms', null, '', 'body')];

        $this->service()->appointmentChanged(Trigger::Booked, 7);

        self::assertSame([], $this->sent);
    }

    public function testAnUnexpectedErrorReleasesTheClaimAndPropagates(): void
    {
        $this->templates = [self::template(1, Trigger::Booked, Audience::Customer)];
        $this->fail = true;
        $channel = new class implements NotificationChannel {
            public function id(): string
            {
                return 'email';
            }

            public function address(): string
            {
                return 'email';
            }

            public function send(Message $message): string
            {
                throw new \LogicException('boom');
            }
        };

        try {
            $this->service(['email' => $channel])->appointmentChanged(Trigger::Booked, 7);
            self::fail('No exception.');
        } catch (\LogicException) {
            self::assertSame(['booked:7:1:' . self::START => 'failed'], $this->log);
        }
    }

    /**
     * @param ?array<string, NotificationChannel> $channels
     */
    private function service(?array $channels = null): NotificationService
    {
        $test = $this;
        $channel = new class ($test) implements NotificationChannel {
            public function __construct(private readonly NotificationServiceTest $test)
            {
            }

            public function id(): string
            {
                return 'email';
            }

            public function address(): string
            {
                return 'email';
            }

            public function send(Message $message): string
            {
                if ($this->test->fail) {
                    throw new DeliveryFailed('mail down');
                }
                $this->test->sent[] = $message;

                return '';
            }
        };

        return new NotificationService(
            new class ($this) implements AppointmentFactsReader {
                public function __construct(private readonly NotificationServiceTest $test)
                {
                }

                public function find(int $appointmentId): ?AppointmentFacts
                {
                    return $this->test->currentFacts();
                }
            },
            new class ($this) implements TemplateRepository {
                public function __construct(private readonly NotificationServiceTest $test)
                {
                }

                public function find(int $id): ?Template
                {
                    foreach ($this->test->currentTemplates() as $template) {
                        if ($template->id === $id) {
                            return $template;
                        }
                    }

                    return null;
                }

                /**
                 * @return list<Template>
                 */
                public function enabledFor(Trigger $trigger): array
                {
                    return \array_values(\array_filter(
                        $this->test->currentTemplates(),
                        static fn (Template $t): bool => $t->trigger === $trigger && $t->enabled
                    ));
                }

                /**
                 * @return list<Template>
                 */
                public function all(): array
                {
                    return $this->test->currentTemplates();
                }

                public function save(Template $template): Template
                {
                    return $template;
                }

                public function delete(int $id): void
                {
                }
            },
            new class ($this) implements NotificationLog {
                public function __construct(private readonly NotificationServiceTest $test)
                {
                }

                public function claim(
                    string $dedupKey,
                    int $templateId,
                    string $channel,
                    string $recipient,
                    int $now
                ): bool {
                    if (isset($this->test->log[$dedupKey]) && self::FAILED !== $this->test->log[$dedupKey]) {
                        return false;
                    }
                    $this->test->log[$dedupKey] = self::SENDING;

                    return true;
                }

                public function markSent(string $dedupKey, string $provider, string $reference, int $now): void
                {
                    $this->test->log[$dedupKey] = self::SENT;
                }

                public function markFailed(string $dedupKey, string $error): void
                {
                    $this->test->log[$dedupKey] = self::FAILED;
                }

                public function page(int $offset, int $limit): Page
                {
                    return new Page([], 0);
                }
            },
            new class ($this) implements ReminderScheduler {
                public function __construct(private readonly NotificationServiceTest $test)
                {
                }

                public function schedule(int $at, int $appointmentId, int $templateId, int $start): void
                {
                    $this->test->scheduled[] = [$at, $appointmentId, $templateId, $start];
                }
            },
            new class implements FactsPresenter {
                /**
                 * @return array<string, string>
                 */
                public function values(AppointmentFacts $facts): array
                {
                    return ['customer_name' => $facts->customerName, 'code' => $facts->code];
                }
            },
            new TemplateRenderer(),
            new class ($this) implements Clock {
                public function __construct(private readonly NotificationServiceTest $test)
                {
                }

                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('@' . $this->test->currentNow());
                }
            },
            $channels ?? ['email' => $channel],
            fn (): Preferences => new Preferences('owner@example.com', $this->quiet)
        );
    }

    public function currentFacts(): ?AppointmentFacts
    {
        return $this->facts;
    }

    /**
     * @return list<Template>
     */
    public function currentTemplates(): array
    {
        return $this->templates;
    }

    public function currentNow(): int
    {
        return $this->now;
    }

    private static function template(int $id, Trigger $trigger, Audience $audience, ?int $offset = null): Template
    {
        return new Template(
            $id,
            $trigger,
            $audience,
            'email',
            $offset,
            'Subject {code}',
            'Hello {customer_name}, code {code}'
        );
    }

    private static function facts(
        string $status,
        int $start,
        ?string $customerEmail = 'ali@example.com'
    ): AppointmentFacts {
        return new AppointmentFacts(
            7,
            'AB12',
            $status,
            $start,
            $start + 1_800,
            'Asia/Tehran',
            1,
            500_000,
            'Ali Karimi',
            '+989121234567',
            $customerEmail,
            'Haircut',
            'Main branch',
            'Sara',
            'sara@example.com'
        );
    }
}
