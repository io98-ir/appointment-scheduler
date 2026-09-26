<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Infrastructure\HolidayDataset;

final class HolidayDatasetTest extends TestCase
{
    public function testParsesJalaliDaysIntoGregorianHolidays(): void
    {
        $holidays = HolidayDataset::parse(
            '{"calendar":"ir","year":1405,"source":"test","holidays":['
            . '{"date":"01-01","title":"Nowruz"},{"date":"12-29","title":"Oil"}]}',
            1405
        );

        self::assertSame(
            [['ir', '2026-03-21', 'Nowruz', 'dataset'], ['ir', '2027-03-20', 'Oil', 'dataset']],
            \array_map(self::describe(...), $holidays)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function brokenDatasets(): iterable
    {
        $holiday = '{"date":"01-01","title":"Nowruz"}';
        yield 'not json' => ['{'];
        yield 'no calendar' => ['{"year":1405,"holidays":[' . $holiday . ']}'];
        yield 'bad calendar' => ['{"calendar":"I R","year":1405,"holidays":[' . $holiday . ']}'];
        yield 'year as text' => ['{"calendar":"ir","year":"1405","holidays":[' . $holiday . ']}'];
        yield 'no holidays' => ['{"calendar":"ir","year":1405}'];
        yield 'day 30 of Esfand in a common year' => [
            '{"calendar":"ir","year":1405,"holidays":[{"date":"12-30","title":"x"}]}',
        ];
        yield 'day 31 of Mehr' => ['{"calendar":"ir","year":1405,"holidays":[{"date":"07-31","title":"x"}]}'];
        yield 'month 13' => ['{"calendar":"ir","year":1405,"holidays":[{"date":"13-01","title":"x"}]}'];
        yield 'date with the year' => ['{"calendar":"ir","year":1405,"holidays":[{"date":"1405-01-01","title":"x"}]}'];
        yield 'empty title' => ['{"calendar":"ir","year":1405,"holidays":[{"date":"01-01","title":" "}]}'];
        yield 'another year' => ['{"calendar":"ir","year":1404,"holidays":[' . $holiday . ']}'];
        yield 'same day twice' => ['{"calendar":"ir","year":1405,"holidays":[' . $holiday . ',' . $holiday . ']}'];
    }

    /**
     * @dataProvider brokenDatasets
     */
    public function testRefusesABrokenDataset(string $json): void
    {
        $this->expectException(\UnexpectedValueException::class);

        HolidayDataset::parse($json, 1405);
    }

    /**
     * The shipped dataset: the official 1405 calendar (Institute of
     * Geophysics, University of Tehran), Fridays not listed.
     */
    public function testTheShipped1405DatasetIsTheOfficialCalendar(): void
    {
        $holidays = HolidayDataset::fromFile(HolidayDataset::path(1405), 1405);
        $dates = \array_map(static fn (Holiday $h): string => $h->date->toString(), $holidays);

        self::assertCount(26, $holidays);
        self::assertSame(['ir'], \array_values(\array_unique(\array_map(
            static fn (Holiday $h): string => $h->calendar->value,
            $holidays
        ))));
        self::assertSame(\array_values(\array_unique($dates)), $dates);
        $sorted = $dates;
        \sort($sorted);
        self::assertSame($sorted, $dates);
        // Nowruz, 14-15 Khordad, Tasua and Ashura, 22 Bahman, 29 Esfand.
        $landmarks = ['2026-03-21', '2026-03-24', '2026-06-04', '2026-06-05', '2026-06-24', '2026-06-25', '2027-02-11'];
        foreach ($landmarks as $date) {
            self::assertContains($date, $dates);
        }
        self::assertSame('2026-03-21', $dates[0]);
        self::assertSame('2027-03-20', $dates[25]);
    }

    /**
     * @return array{string, string, string, string}
     */
    private static function describe(Holiday $holiday): array
    {
        return [
            $holiday->calendar->value,
            $holiday->date->toString(),
            $holiday->title->value,
            $holiday->source->value,
        ];
    }
}
