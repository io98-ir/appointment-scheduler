<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategory;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * The catalog entities other than Service (ServiceTest), which only guard
 * their own fields.
 */
final class EntitiesTest extends TestCase
{
    use AssertsInvalidValue;

    public function testALocationKeepsItsIanaTimezone(): void
    {
        $location = new Location(
            id: null,
            name: Name::fromInput('شعبه ونک'),
            timezone: new \DateTimeZone('Asia/Tehran'),
            phone: PhoneNumber::fromInput('02188776655'),
            holidayCalendar: Slug::fromInput('ir'),
        );

        self::assertSame(
            ['Asia/Tehran', '+982188776655', 'ir', Status::Active],
            [
                $location->timezone->getName(),
                (string) $location->phone,
                (string) $location->holidayCalendar,
                $location->status,
            ]
        );
    }

    /**
     * An offset has no rules of its own: it would silently stay wrong if the
     * region changed its clocks, as Iran did when it dropped DST in 2023.
     */
    public function testALocationRejectsAFixedOffsetAsItsTimezone(): void
    {
        foreach (['+03:30', 'Etc/GMT-3', 'EST', 'Iran'] as $zone) {
            self::assertInvalid('invalid_timezone', static fn () => new Location(
                id: null,
                name: Name::fromInput('Main'),
                timezone: new \DateTimeZone($zone),
            ));
        }
    }

    public function testALocationMayUseUtc(): void
    {
        $location = new Location(id: null, name: Name::fromInput('Online'), timezone: new \DateTimeZone('UTC'));

        self::assertSame('UTC', $location->timezone->getName());
    }

    /**
     * WordPress turns MySQL strict mode off, so the column would clamp these silently.
     */
    public function testIdsArePositiveAndSortOrdersAreInRange(): void
    {
        $color = Color::fromInput('#aa0000');
        self::assertInvalid(
            'invalid_id',
            static fn () => new ServiceCategory(id: 0, name: Name::fromInput('A'), color: $color)
        );
        self::assertInvalid(
            'invalid_id',
            static fn () => new Staff(id: null, name: Name::fromInput('A'), color: $color, locationId: -1)
        );
        self::assertInvalid(
            'invalid_sort',
            static fn () => new ServiceCategory(id: null, name: Name::fromInput('A'), color: $color, sort: -1)
        );
        self::assertInvalid(
            'invalid_sort',
            static fn () => new Staff(id: null, name: Name::fromInput('A'), color: $color, sort: 3_000_000_000)
        );
    }

    public function testALocationAddressHasALimit(): void
    {
        self::assertInvalid('text_too_long', static fn () => new Location(
            id: null,
            name: Name::fromInput('Main'),
            timezone: new \DateTimeZone('UTC'),
            address: \str_repeat('a', Location::MAX_ADDRESS_LENGTH + 1),
        ));
    }

    public function testStaffNeedOnlyANameAndAColor(): void
    {
        $staff = new Staff(id: 3, name: Name::fromInput('دکتر کریمی'), color: Color::fromInput('#aa0000'));

        self::assertSame(
            [3, null, null, '', null, Status::Active, 0],
            [
                $staff->id,
                $staff->wpUserId,
                $staff->locationId,
                $staff->title,
                $staff->email,
                $staff->status,
                $staff->sort,
            ]
        );
    }

    public function testAStaffTitleHasALimit(): void
    {
        self::assertInvalid('text_too_long', static fn () => new Staff(
            id: null,
            name: Name::fromInput('Sara'),
            color: Color::fromInput('#aa0000'),
            title: \str_repeat('ت', Staff::MAX_TITLE_LENGTH + 1),
        ));
    }

    public function testAStaffBioHasALimitInBytes(): void
    {
        // TEXT holds 65,535 bytes; a Persian letter takes two.
        self::assertInvalid('text_too_long', static fn () => new Staff(
            id: null,
            name: Name::fromInput('Sara'),
            color: Color::fromInput('#aa0000'),
            bio: \str_repeat('ب', 40000),
        ));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidCapacities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'over the limit' => [BookableResource::MAX_CAPACITY + 1];
    }

    /**
     * @dataProvider invalidCapacities
     */
    public function testAResourceHoldsAtLeastOneBooking(int $capacity): void
    {
        self::assertInvalid('invalid_capacity', static fn () => new BookableResource(
            id: null,
            name: Name::fromInput('Room 1'),
            groupKey: Slug::fromInput('room'),
            capacity: $capacity,
        ));
    }

    public function testAResourceBelongsToAGroupThatServicesAskFor(): void
    {
        $resource = new BookableResource(
            id: null,
            name: Name::fromInput('Room 1'),
            groupKey: Slug::fromInput('room'),
            locationId: 2,
        );

        self::assertSame(['room', 2, 1], [$resource->groupKey->value, $resource->locationId, $resource->capacity]);
    }

    public function testACategoryHasANameAndAColor(): void
    {
        $category = new ServiceCategory(
            id: null,
            name: Name::fromInput('پوست'),
            color: Color::fromInput('#00aa00'),
            sort: 2,
        );

        self::assertSame(['پوست', '#00aa00', 2], [$category->name->value, $category->color->value, $category->sort]);
    }

    public function testAnExtraMayBeFreeAndTakeNoTime(): void
    {
        $extra = new Extra(id: null, name: Name::fromInput('Gift wrap'), price: Money::zero(), durationMin: 0);

        self::assertSame(
            [null, 0, 0, 1],
            [$extra->serviceId, $extra->price->amount, $extra->durationMin, $extra->maxQty]
        );
    }

    public function testAnExtraCannotLowerThePrice(): void
    {
        // Discounts are price rules and coupons (T2.3), which show as their own lines.
        self::assertInvalid('invalid_price', static fn () => new Extra(
            id: null,
            name: Name::fromInput('Discount'),
            price: Money::ofRial(-10000),
            durationMin: 0,
        ));
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function invalidExtraAmounts(): iterable
    {
        yield 'negative duration' => [-5, 1, 'invalid_duration'];
        yield 'longer than a day' => [1441, 1, 'invalid_duration'];
        yield 'zero quantity' => [10, 0, 'invalid_quantity'];
        yield 'quantity over the limit' => [10, Extra::MAX_QTY + 1, 'invalid_quantity'];
    }

    /**
     * @dataProvider invalidExtraAmounts
     */
    public function testAnExtraGuardsItsDurationAndQuantity(int $duration, int $maxQty, string $code): void
    {
        self::assertInvalid($code, static fn () => new Extra(
            id: null,
            name: Name::fromInput('Mask'),
            price: Money::ofRial(50000),
            durationMin: $duration,
            maxQty: $maxQty,
        ));
    }
}
