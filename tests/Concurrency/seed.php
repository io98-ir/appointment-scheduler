<?php

/**
 * Seeds one bookable slot for the concurrency job and prints what the
 * requests need, as JSON on a line of its own after "SEED " (wp-env adds
 * lines of its own to the output). Run with `wp eval-file` inside wp-env, so the
 * plugin is loaded; the job then races tests/Concurrency/race.php against
 * it (ADR-004). No strict_types: wp eval-file runs the file through eval(),
 * where the declaration is a fatal error.
 */

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbLocationRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;
use Vaqtyar\Modules\Scheduling\Domain\Owner;
use Vaqtyar\Modules\Scheduling\Domain\OwnerType;
use Vaqtyar\Modules\Scheduling\Domain\RuleKind;
use Vaqtyar\Modules\Scheduling\Domain\ScheduleRule;
use Vaqtyar\Modules\Scheduling\Infrastructure\Persistence\WpdbScheduleRuleRepository;
use Vaqtyar\Shared\Domain\LocalTime;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\SystemClock;

// A closure, so nothing leaks into the globals of wp eval-file.
(static function (): void {
    $db = Db::fromGlobals();
    foreach (['holds', 'occupancies', 'resource_day_locks', 'rate_limits'] as $table) {
        $db->execute('TRUNCATE TABLE %i', Tables::name($table));
    }
    $clock = new SystemClock();
    $zone = new DateTimeZone('Asia/Tehran');
    $location = (int) (new WpdbLocationRepository($db, $clock))->save(
        new Location(null, Name::fromInput('Race'), $zone)
    )->id;
    $staff = [];
    // Two staff members, so a request that lets the business choose could take
    // either: exactly two holds may succeed then, one each.
    for ($i = 1; $i <= 2; ++$i) {
        $id = (int) (new WpdbStaffRepository($db, $clock))->save(
            new Staff(null, Name::fromInput("Racer {$i}"), Color::fromInput('#112233'), locationId: $location)
        )->id;
        $rules = [];
        for ($weekday = 0; $weekday < 7; ++$weekday) {
            $rules[] = new ScheduleRule(
                null,
                new Owner(OwnerType::Staff, $id),
                $weekday,
                LocalTime::fromString('09:00'),
                LocalTime::fromString('17:00'),
                RuleKind::Work
            );
        }
        (new WpdbScheduleRuleRepository($db, new Transaction($db), $clock))
            ->replace(new Owner(OwnerType::Staff, $id), $rules);
        $staff[] = $id;
    }
    $service = (new WpdbServiceRepository($db, new Transaction($db), $clock))->save(new Service(
        null,
        Name::fromInput('Race'),
        [new Variant(null, '', 60, Money::ofRial(1_000_000), true)],
        array_map(static fn (int $id): ServiceStaff => new ServiceStaff($id), $staff)
    ));
    $day = (new DateTimeImmutable('now', $zone))->modify('+2 days')->format('Y-m-d');

    echo "\nSEED " . wp_json_encode([
        'route' => '/' . Identity::REST_NAMESPACE . '/holds',
        'nonce' => wp_create_nonce('wp_rest'),
        'variant' => (int) $service->variants[0]->id,
        'location' => $location,
        'staff' => $staff,
        'start' => $day . 'T10:00:00+03:30',
        'holds_table' => Tables::name('holds'),
        'occupancies_table' => Tables::name('occupancies'),
    ]) . "\n";
})();
