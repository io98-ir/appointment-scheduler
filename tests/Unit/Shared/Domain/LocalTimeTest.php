<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\LocalTime;

final class LocalTimeTest extends TestCase
{
    public function testParsesHoursAndMinutes(): void
    {
        $time = LocalTime::fromString('09:30');

        self::assertSame(570, $time->minutes);
        self::assertSame('09:30', $time->toString());
        self::assertSame('00:00', LocalTime::fromMinutes(0)->toString());
    }

    public function testMidnightAtTheEndOfTheDayIs2400(): void
    {
        // A schedule that ends at midnight: "18:00-24:00".
        $end = LocalTime::fromString('24:00');

        self::assertSame(1440, $end->minutes);
        self::assertSame('24:00', $end->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTimes(): iterable
    {
        yield 'hour 25' => ['25:00'];
        yield 'after 24:00' => ['24:01'];
        yield 'minute 60' => ['10:60'];
        yield 'seconds' => ['10:00:00'];
        yield 'single digit hour' => ['9:30'];
        yield 'trailing newline' => ["09:30\n"];
    }

    /**
     * @dataProvider invalidTimes
     */
    public function testRejectsInvalidTimes(string $input): void
    {
        $this->expectException(InvalidValue::class);

        LocalTime::fromString($input);
    }

    public function testRejectsMinutesOutsideTheDay(): void
    {
        $this->expectException(InvalidValue::class);

        LocalTime::fromMinutes(1441);
    }

    public function testOrdersTimesOfDay(): void
    {
        $nine = LocalTime::fromString('09:00');
        $five = LocalTime::fromString('17:00');

        self::assertTrue($nine->isBefore($five));
        self::assertFalse($five->isBefore($nine));
        self::assertTrue($nine->equals(LocalTime::fromMinutes(540)));
    }
}
