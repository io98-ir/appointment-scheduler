<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel\Log;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Log\Pii;

final class PiiTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'mobile' => ['sms to 09121234567 failed', 'sms to ***4567 failed'];
        yield 'e164' => ['sms to +989121234567 failed', 'sms to ***4567 failed'];
        yield 'persian digits' => ['شماره ۰۹۱۲۱۲۳۴۵۶۷', 'شماره ***۴۵۶۷'];
        yield 'spaced' => ['call 0912 123 4567 now', 'call ***4567 now'];
        yield 'parenthesised' => ['call (0912) 123 4567 now', 'call ***4567 now'];
        yield 'dashed' => ['call 0912-123-4567 now', 'call ***4567 now'];
        yield 'card' => ['card 6037991234567890', 'card ***7890'];
        yield 'mysql duplicate' => ["Duplicate entry '09121234567' for key", "Duplicate entry '***4567' for key"];
        yield 'email' => ['mail to ali.rezaei@example.com bounced', 'mail to ***@example.com bounced'];
        yield 'email in brackets' => ['<ali@example.ir>', '<***@example.ir>'];
        yield 'short numbers stay' => ['appointment 1234567 at slot 12', 'appointment 1234567 at slot 12'];
        yield 'iso date stays' => ['on 2026-09-25 10:00:00', 'on 2026-09-25 10:00:00'];
        yield 'jalali date stays' => ['on 1405/07/03', 'on 1405/07/03'];
        yield 'request id stays' => ['request 9f86d081884c7d65', 'request 9f86d081884c7d65'];
        yield 'plain' => ['nothing personal', 'nothing personal'];
    }

    /**
     * @dataProvider texts
     */
    public function testMasksPersonalData(string $text, string $masked): void
    {
        self::assertSame($masked, Pii::mask($text));
    }

    public function testInvalidUtf8IsNotPassedThrough(): void
    {
        self::assertSame('[unreadable text]', Pii::mask("09121234567 \xC3\x28"));
    }
}
