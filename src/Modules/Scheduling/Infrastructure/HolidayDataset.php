<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Infrastructure;

use Vaqtyar\Modules\Scheduling\Domain\Holiday;
use Vaqtyar\Modules\Scheduling\Domain\HolidaySource;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Jalali;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A holiday dataset shipped with the plugin, one file per Jalali year in
 * assets/holidays/ (iran-ecosystem: lunar holidays cannot be computed, so the
 * official calendar is copied in each year):
 *
 *     {"calendar": "ir", "year": 1405, "source": "…",
 *      "holidays": [{"date": "01-01", "title": "…"}, …]}
 *
 * "date" is the Jalali month and day in that year. The file is ours, so any
 * fault in it is a bug and throws UnexpectedValueException.
 */
final class HolidayDataset
{
    public static function path(int $year): string
    {
        return \dirname(__DIR__, 4) . "/assets/holidays/{$year}.json";
    }

    /**
     * @return list<Holiday>
     */
    public static function fromFile(string $path, int $year): array
    {
        // A local file of the plugin, not a URL.
        $json = \is_readable($path) ? \file_get_contents($path) : false;
        if (false === $json) {
            throw new \UnexpectedValueException('The holiday dataset file cannot be read.');
        }

        return self::parse($json, $year);
    }

    /**
     * @param int $year The Jalali year the caller expects, so a file copied
     *     from last year without changing its "year" is refused.
     * @return list<Holiday> In the file's order.
     */
    public static function parse(string $json, int $year): array
    {
        try {
            $data = \json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException('The holiday dataset is not JSON.', 0, $e);
        }
        if (
            !\is_array($data)
            || !\is_string($data['calendar'] ?? null)
            || !\is_int($data['year'] ?? null)
            || !\is_array($data['holidays'] ?? null)
        ) {
            throw new \UnexpectedValueException('A holiday dataset needs a calendar, a year and holidays.');
        }

        if ($data['year'] !== $year) {
            throw new \UnexpectedValueException('The holiday dataset is for another year.');
        }
        $jalali = new Jalali();
        $holidays = [];
        $seen = [];
        try {
            $calendar = Slug::fromInput($data['calendar']);
            if ($calendar->value !== $data['calendar']) {
                throw new \UnexpectedValueException('The calendar of a holiday dataset is not a key.');
            }
            foreach ($data['holidays'] as $entry) {
                if (
                    !\is_array($entry)
                    || !\is_string($entry['date'] ?? null)
                    || !\is_string($entry['title'] ?? null)
                    || 1 !== \preg_match('/^(\d{2})-(\d{2})$/D', $entry['date'], $m)
                ) {
                    throw new \UnexpectedValueException('A dataset holiday needs a "MM-DD" date and a title.');
                }
                $date = $jalali->toGregorian($year, (int) $m[1], (int) $m[2]);
                if (isset($seen[$date->toString()])) {
                    throw new \UnexpectedValueException('A holiday dataset lists a day twice.');
                }
                $seen[$date->toString()] = true;
                $holidays[] = new Holiday($calendar, $date, Name::fromInput($entry['title']), HolidaySource::Dataset);
            }
        } catch (InvalidValue $e) {
            throw new \UnexpectedValueException('A holiday dataset has an invalid value: ' . $e->errorCode, 0, $e);
        }

        return $holidays;
    }
}
