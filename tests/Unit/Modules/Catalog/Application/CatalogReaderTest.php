<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Application;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Application\CatalogReader;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Shared\Domain\Money;

final class CatalogReaderTest extends TestCase
{
    private const SHORT = 11;
    private const LONG = 12;

    private ServiceRepository&MockInterface $services;

    private StaffRepository&MockInterface $staff;

    private BookableResourceRepository&MockInterface $resources;

    private LocationRepository&MockInterface $locations;

    private CatalogReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = self::mock(ServiceRepository::class);
        $this->staff = self::mock(StaffRepository::class);
        $this->resources = self::mock(BookableResourceRepository::class);
        $this->locations = self::mock(LocationRepository::class);
        $this->reader = new CatalogReader($this->services, $this->staff, $this->resources, $this->locations);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAnUnknownVariantHasNoOffer(): void
    {
        $this->services->shouldReceive('findByVariant')->with(99)->andReturnNull();

        self::assertNull($this->reader->offer(99));
    }

    public function testAnInactiveServiceHasNoOffer(): void
    {
        $this->services->shouldReceive('findByVariant')->with(self::SHORT)->andReturn(self::service(Status::Inactive));

        self::assertNull($this->reader->offer(self::SHORT));
    }

    public function testTheOfferCarriesTheVariantRulesAndEachActiveStaffMembersTerms(): void
    {
        $this->services->shouldReceive('findByVariant')->with(self::LONG)->andReturn(self::service());
        $this->locations->shouldReceive('find')->with(1)->andReturn(self::location(1));
        $this->staff->shouldReceive('findMany')->with([3, 4, 5])->andReturn([
            self::staffMember(3, locationId: 1),
            self::staffMember(4),
            self::staffMember(5, Status::Inactive),
        ]);

        $offer = $this->reader->offer(self::LONG);

        self::assertNotNull($offer);
        self::assertSame(
            [7, self::LONG, 2, 5, 10, 15, []],
            [
                $offer->serviceId,
                $offer->variantId,
                $offer->capacity,
                $offer->bufferBeforeMin,
                $offer->bufferAfterMin,
                $offer->slotStepMin,
                $offer->resources,
            ]
        );
        // 3 has their own price for the long variant; 4 only serves the short one.
        self::assertEquals([new StaffOffer(3, 1, 60, Money::ofRial(3_000_000))], $offer->staff);
    }

    public function testTheOfferListsTheActiveResourcesOfEachGroup(): void
    {
        $this->services->shouldReceive('findByVariant')->with(self::SHORT)->andReturn(
            self::service(resources: [new ResourceRequirement(Slug::fromInput('room'), 2)])
        );
        $this->staff->shouldReceive('findMany')->andReturn([]);
        $this->locations->shouldReceive('find')->with(1)->andReturn(self::location(1));
        $rooms = [
            new BookableResource(20, Name::fromInput('Room 1'), Slug::fromInput('room'), 1, 2),
            new BookableResource(21, Name::fromInput('Room 2'), Slug::fromInput('room'), status: Status::Inactive),
        ];
        $this->resources->shouldReceive('inGroup')
            ->withArgs(static fn (Slug $group): bool => 'room' === $group->value)
            ->andReturn($rooms);

        $offer = $this->reader->offer(self::SHORT);

        self::assertNotNull($offer);
        self::assertSame(['room', 2], [$offer->resources[0]->groupKey, $offer->resources[0]->quantity]);
        self::assertEquals([new ResourceUnit(20, 1, 2)], $offer->resources[0]->units);
    }

    public function testStaffAndResourcesAtAnInactiveLocationAreLeftOut(): void
    {
        $this->services->shouldReceive('findByVariant')->with(self::SHORT)->andReturn(
            self::service(resources: [new ResourceRequirement(Slug::fromInput('room'))])
        );
        // Looked up once for the staff member and the room together.
        $this->locations->shouldReceive('find')->once()->with(2)->andReturn(self::location(2, Status::Inactive));
        $this->staff->shouldReceive('findMany')->andReturn([self::staffMember(3, locationId: 2)]);
        $this->resources->shouldReceive('inGroup')->andReturn([
            new BookableResource(20, Name::fromInput('Room 1'), Slug::fromInput('room'), 2),
        ]);

        $offer = $this->reader->offer(self::SHORT);

        self::assertNotNull($offer);
        self::assertSame([[], []], [$offer->staff, $offer->resources[0]->units]);
    }

    public function testAnInactiveLocationIsNotRead(): void
    {
        $this->locations->shouldReceive('find')->with(2)->andReturn(self::location(2, Status::Inactive));

        self::assertNull($this->reader->location(2));
    }

    public function testALocationIsReadWithItsTimezoneAndCalendar(): void
    {
        $this->locations->shouldReceive('find')->with(1)->andReturn(new Location(
            1,
            Name::fromInput('Main'),
            new \DateTimeZone('Asia/Tehran'),
            holidayCalendar: Slug::fromInput('ir')
        ));
        $this->locations->shouldReceive('find')->with(2)->andReturnNull();

        $location = $this->reader->location(1);

        self::assertNotNull($location);
        self::assertSame([1, 'Asia/Tehran', 'ir'], [
            $location->locationId,
            $location->timezone->getName(),
            $location->holidayCalendar,
        ]);
        self::assertNull($this->reader->location(2));
    }

    /**
     * @param list<ResourceRequirement> $resources
     */
    private static function service(Status $status = Status::Active, array $resources = []): Service
    {
        return new Service(
            7,
            Name::fromInput('Consultation'),
            [
                new Variant(self::SHORT, '30 min', 30, Money::ofRial(1_000_000), true),
                new Variant(self::LONG, '60 min', 60, Money::ofRial(2_000_000), false, 5, 10, 15),
            ],
            [
                new ServiceStaff(3),
                new ServiceStaff(3, self::LONG, Money::ofRial(3_000_000)),
                new ServiceStaff(4, self::SHORT),
                new ServiceStaff(5),
            ],
            $resources,
            capacity: 2,
            status: $status
        );
    }

    private static function location(int $id, Status $status = Status::Active): Location
    {
        return new Location($id, Name::fromInput('Branch'), new \DateTimeZone('Asia/Tehran'), status: $status);
    }

    private static function staffMember(int $id, Status $status = Status::Active, ?int $locationId = null): Staff
    {
        return new Staff(
            $id,
            Name::fromInput("Staff {$id}"),
            Color::fromInput('#112233'),
            locationId: $locationId,
            status: $status
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return T&MockInterface
     */
    private static function mock(string $interface): object
    {
        /** @var T&MockInterface $mock Mockery has no PHPStan extension here. */
        $mock = Mockery::mock($interface);

        return $mock;
    }
}
