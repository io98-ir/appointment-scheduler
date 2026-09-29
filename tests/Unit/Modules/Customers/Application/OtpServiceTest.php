<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Customers\Application\Captcha;
use Vaqtyar\Modules\Customers\Application\OtpSender;
use Vaqtyar\Modules\Customers\Application\OtpService;
use Vaqtyar\Modules\Customers\Application\PhoneSessions;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class OtpServiceTest extends TestCase
{
    private const KEY = 'test-key';
    private const START = 1_800_000_000;

    private int $now = self::START;

    /** @var list<string> */
    private array $sent = [];

    private OtpService $service;

    protected function setUp(): void
    {
        $this->service = new OtpService(
            new InMemoryOtpStore(),
            new class (function (string $code): void {
                $this->sent[] = $code;
            }) implements OtpSender {
                public function __construct(private readonly \Closure $onSend)
                {
                }

                public function send(string $phone, string $code): void
                {
                    ($this->onSend)($code);
                }
            },
            new PhoneSessions(self::KEY),
            $this->clock(),
            self::KEY
        );
    }

    public function testACodeIsSentAndVerifiedOnceForASessionOfThatNumber(): void
    {
        $phone = PhoneNumber::fromInput('09121234567');

        self::assertSame(0, $this->service->request($phone));
        self::assertCount(1, $this->sent);
        self::assertMatchesRegularExpression('/^\d{6}$/', $this->sent[0]);

        $session = $this->service->verify($phone, $this->sent[0]);

        self::assertNotNull($session);
        self::assertSame(
            '+989121234567',
            (new PhoneSessions(self::KEY))->phoneOf($session['token'], $this->now)
        );
        self::assertNull($this->service->verify($phone, $this->sent[0]), 'a code works once');
    }

    public function testPersianDigitsAreRead(): void
    {
        $phone = PhoneNumber::fromInput('09121234567');
        $this->service->request($phone);
        $persian = \strtr($this->sent[0], [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);

        self::assertNotNull($this->service->verify($phone, ' ' . $persian . ' '));
    }

    public function testFiveWrongGuessesLockTheCodeEvenForTheRightOne(): void
    {
        $phone = PhoneNumber::fromInput('09121234567');
        $this->service->request($phone);
        $wrong = $this->sent[0] === '000000' ? '111111' : '000000';

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; ++$i) {
            self::assertNull($this->service->verify($phone, $wrong));
        }

        self::assertNull($this->service->verify($phone, $this->sent[0]));
    }

    public function testACodeExpiresAfterFiveMinutes(): void
    {
        $phone = PhoneNumber::fromInput('09121234567');
        $this->service->request($phone);
        $this->now += OtpService::TTL_SECONDS;

        self::assertNull($this->service->verify($phone, $this->sent[0]));
    }

    public function testACodeOfAnotherNumberDoesNotWork(): void
    {
        $this->service->request(PhoneNumber::fromInput('09121234567'));

        self::assertNull($this->service->verify(PhoneNumber::fromInput('09351112233'), $this->sent[0]));
    }

    public function testCodesAreLimitedPerMinuteAndPerWindow(): void
    {
        $phone = PhoneNumber::fromInput('09121234567');
        self::assertSame(0, $this->service->request($phone));

        self::assertSame(OtpService::RESEND_SECONDS, $this->service->request($phone));

        for ($i = 0; $i < OtpService::MAX_CODES - 1; ++$i) {
            $this->now += OtpService::RESEND_SECONDS;
            self::assertSame(0, $this->service->request($phone));
        }
        $this->now += OtpService::RESEND_SECONDS;
        self::assertSame(OtpService::WINDOW_SECONDS, $this->service->request($phone));
        self::assertCount(OtpService::MAX_CODES, $this->sent);

        $this->now += OtpService::WINDOW_SECONDS;
        self::assertSame(0, $this->service->request($phone));
    }

    public function testASessionExpiresAndCannotBeForged(): void
    {
        $sessions = new PhoneSessions(self::KEY);
        $issued = $sessions->issue('+989121234567', $this->now);

        self::assertSame('+989121234567', $sessions->phoneOf($issued['token'], $this->now));
        self::assertNull($sessions->phoneOf($issued['token'], $issued['expires_at'] + 1));
        self::assertNull((new PhoneSessions('other-key'))->phoneOf($issued['token'], $this->now));
        self::assertNull($sessions->phoneOf('x.y', $this->now));
        self::assertNull($sessions->phoneOf(null, $this->now));
        [$payload] = \explode('.', $issued['token']);
        self::assertNull($sessions->phoneOf($payload . '.' . \str_repeat('0', 64), $this->now));
    }

    public function testACaptchaIsSolvedOnceItsSumIsRightAndBeforeItExpires(): void
    {
        $captcha = new Captcha(self::KEY);
        $issued = $captcha->issue($this->now);

        self::assertTrue($captcha->verify($issued['token'], (string) ($issued['a'] + $issued['b']), $this->now));
        self::assertFalse($captcha->verify($issued['token'], (string) ($issued['a'] + $issued['b'] + 1), $this->now));
        self::assertFalse($captcha->verify($issued['token'], 'abc', $this->now));
        self::assertFalse($captcha->verify('bad', '5', $this->now));
        self::assertFalse(
            $captcha->verify(
                $issued['token'],
                (string) ($issued['a'] + $issued['b']),
                $this->now + Captcha::TTL_SECONDS + 1
            )
        );
        self::assertFalse(
            (new Captcha('other'))->verify($issued['token'], (string) ($issued['a'] + $issued['b']), $this->now)
        );
    }

    private function clock(): Clock
    {
        return new class ($this) implements Clock {
            public function __construct(private readonly OtpServiceTest $test)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('@' . $this->test->now());
            }
        };
    }

    public function now(): int
    {
        return $this->now;
    }
}
