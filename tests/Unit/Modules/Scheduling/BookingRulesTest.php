<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Scheduling;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Scheduling\Application\BookingRulesService;
use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

final class BookingRulesTest extends TestCase
{
    public function testAcceptsTheEdgesOfEachRange(): void
    {
        $low = BookingRules::of(1, 0, 1, StaffChoice::Priority);
        $high = BookingRules::of(1440, 365 * 1440, 730, StaffChoice::LeastBusy);

        self::assertSame([1, 0, 1], [$low->slotStepMin, $low->minNoticeMin, $low->maxAdvanceDays]);
        self::assertSame(StaffChoice::Priority, $low->staffChoice);
        self::assertSame([1440, 365 * 1440, 730], [$high->slotStepMin, $high->minNoticeMin, $high->maxAdvanceDays]);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function outOfRange(): iterable
    {
        yield 'step zero' => [0, 60, 60, 'invalid_slot_step'];
        yield 'step over a day' => [1441, 60, 60, 'invalid_slot_step'];
        yield 'negative notice' => [30, -1, 60, 'invalid_min_notice'];
        yield 'notice over a year' => [30, 365 * 1440 + 1, 60, 'invalid_min_notice'];
        yield 'no days ahead' => [30, 60, 0, 'invalid_max_advance'];
        yield 'over two years ahead' => [30, 60, 731, 'invalid_max_advance'];
    }

    /**
     * @dataProvider outOfRange
     */
    public function testRejectsWhatIsOutOfRange(int $step, int $notice, int $advance, string $code): void
    {
        try {
            BookingRules::of($step, $notice, $advance, StaffChoice::LeastBusy);
            self::fail('Expected InvalidValue.');
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    public function testTheServiceSavesAndReadsBack(): void
    {
        $store = new MemoryBookingRulesStore();
        $service = new BookingRulesService($this->authorizer(true), $store);

        $saved = $service->save(BookingRules::of(15, 120, 90, StaffChoice::Priority));

        self::assertSame($saved, $store->saved);
        self::assertSame($saved, $service->rules());
    }

    public function testEveryCallChecksTheCapability(): void
    {
        $store = new MemoryBookingRulesStore();
        $service = new BookingRulesService($this->authorizer(false), $store);

        foreach (
            [
                static fn () => $service->rules(),
                static fn () => $service->save(BookingRules::of(15, 120, 90, StaffChoice::Priority)),
            ] as $call
        ) {
            try {
                $call();
                self::fail('Expected Forbidden.');
            } catch (Forbidden) {
                self::addToAssertionCount(1);
            }
        }
        self::assertNull($store->saved);
    }

    private function authorizer(bool $allowed): Authorizer
    {
        return new class ($allowed) implements Authorizer {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->allowed && BookingRulesService::CAPABILITY === $capability;
            }
        };
    }
}
