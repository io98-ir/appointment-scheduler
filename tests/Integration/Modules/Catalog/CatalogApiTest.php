<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Catalog;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Transaction;
use Vaqtyar\Modules\Catalog\Application\CatalogReader;
use Vaqtyar\Modules\Catalog\Contracts\ResourceUnit;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Slug;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbBookableResourceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbLocationRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbServiceRepository;
use Vaqtyar\Modules\Catalog\Infrastructure\Persistence\WpdbStaffRepository;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Integration\Kernel\Database\RealDatabase;

/**
 * CatalogApi on the stored catalog: what Scheduling and Booking will see.
 */
final class CatalogApiTest extends TestCase
{
    use CatalogTables;
    use RealDatabase;

    private WpdbLocationRepository $locations;

    private WpdbStaffRepository $staff;

    private WpdbBookableResourceRepository $resources;

    private WpdbServiceRepository $services;

    private CatalogReader $api;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->realDb();
        $clock = new FixedClock('2026-09-25 10:00:00');
        $this->locations = new WpdbLocationRepository($db, $clock);
        $this->staff = new WpdbStaffRepository($db, $clock);
        $this->resources = new WpdbBookableResourceRepository($db, $clock);
        $this->services = new WpdbServiceRepository($db, new Transaction($db), $clock);
        $this->api = new CatalogReader($this->services, $this->staff, $this->resources, $this->locations);
    }

    protected function tearDown(): void
    {
        $this->emptyCatalogTables();
        parent::tearDown();
    }

    public function testAnOfferHasTheActiveStaffWithTheirTermsAndTheActiveResources(): void
    {
        $location = $this->locations->save(
            new Location(null, Name::fromInput('Main'), new \DateTimeZone('Asia/Tehran'))
        );
        $karimi = $this->staffMember('Karimi', Status::Active, $location->id);
        $ahmadi = $this->staffMember('Ahmadi', Status::Active);
        $away = $this->staffMember('Away', Status::Inactive);
        $deleted = $this->staffMember('Gone', Status::Active);
        $this->staff->delete($deleted);
        $room = $this->resources->save(
            new BookableResource(null, Name::fromInput('Room 1'), Slug::fromInput('room'), capacity: 2)
        );
        $closed = $this->resources->save(
            new BookableResource(null, Name::fromInput('Room 2'), Slug::fromInput('room'), status: Status::Inactive)
        );
        self::assertNotNull($closed->id);

        $service = $this->services->save(new Service(
            null,
            Name::fromInput('Consultation'),
            [
                new Variant(null, '30 min', 30, Money::ofRial(1_000_000), true),
                new Variant(null, '60 min', 60, Money::ofRial(2_000_000), false, 5, 10, 15),
            ],
            [new ServiceStaff($away), new ServiceStaff($deleted)],
            [new ResourceRequirement(Slug::fromInput('room'))],
            capacity: 3,
        ));
        $long = $service->variants[1]->id;
        self::assertNotNull($long);
        // Assignments can name a variant only once it is stored.
        $service = $this->services->save(new Service(
            $service->id,
            $service->name,
            $service->variants,
            [
                new ServiceStaff($karimi, $long, Money::ofRial(2_500_000), 70),
                new ServiceStaff($ahmadi, $long),
                new ServiceStaff($away, $long),
                new ServiceStaff($deleted, $long),
            ],
            $service->resources,
            capacity: 3,
        ));

        $offer = $this->api->offer($long);

        self::assertNotNull($offer);
        self::assertSame(
            [$service->id, $long, 3, 5, 10, 15],
            [
                $offer->serviceId,
                $offer->variantId,
                $offer->capacity,
                $offer->bufferBeforeMin,
                $offer->bufferAfterMin,
                $offer->slotStepMin,
            ]
        );
        self::assertEquals(
            [
                new StaffOffer($karimi, $location->id, 70, Money::ofRial(2_500_000)),
                new StaffOffer($ahmadi, null, 60, Money::ofRial(2_000_000)),
            ],
            $offer->staff
        );
        self::assertSame(['room', 1], [$offer->resources[0]->groupKey, $offer->resources[0]->quantity]);
        self::assertEquals([new ResourceUnit((int) $room->id, null, 2)], $offer->resources[0]->units);
    }

    public function testADeletedServiceOrVariantHasNoOffer(): void
    {
        $service = $this->services->save(new Service(null, Name::fromInput('A'), [
            new Variant(null, 'short', 30, Money::ofRial(1_000_000), true),
            new Variant(null, 'long', 60, Money::ofRial(2_000_000), false),
        ]));
        [$short, $long] = [(int) $service->variants[0]->id, (int) $service->variants[1]->id];
        // Saving without the long variant deletes it.
        $this->services->save(new Service($service->id, $service->name, [$service->variants[0]]));

        $beforeDelete = [$this->api->offer($short) !== null, $this->api->offer($long)];
        $this->services->delete((int) $service->id);

        self::assertSame([true, null], $beforeDelete);
        self::assertNull($this->api->offer($short));
    }

    public function testNothingAtAnInactiveLocationIsOffered(): void
    {
        $closed = $this->locations->save(
            new Location(null, Name::fromInput('Closed'), new \DateTimeZone('Asia/Tehran'), status: Status::Inactive)
        );
        $staff = $this->staffMember('Karimi', Status::Active, $closed->id);
        $this->resources->save(
            new BookableResource(null, Name::fromInput('Room 1'), Slug::fromInput('room'), $closed->id)
        );
        $service = $this->services->save(new Service(
            null,
            Name::fromInput('A'),
            [new Variant(null, '', 30, Money::ofRial(1_000_000), true)],
            [new ServiceStaff($staff)],
            [new ResourceRequirement(Slug::fromInput('room'))],
        ));

        $offer = $this->api->offer((int) $service->variants[0]->id);

        self::assertNotNull($offer);
        self::assertSame([[], []], [$offer->staff, $offer->resources[0]->units]);
        self::assertNull($this->api->location((int) $closed->id));
    }

    public function testALocationIsReadWithItsTimezone(): void
    {
        $stored = $this->locations->save(new Location(
            null,
            Name::fromInput('Main'),
            new \DateTimeZone('Asia/Tehran'),
            holidayCalendar: Slug::fromInput('ir')
        ));
        $id = (int) $stored->id;

        $location = $this->api->location($id);
        $this->locations->delete($id);

        self::assertSame(['Asia/Tehran', 'ir'], [$location?->timezone->getName(), $location?->holidayCalendar]);
        self::assertNull($this->api->location($id));
    }

    private function staffMember(string $name, Status $status, ?int $locationId = null): int
    {
        $staff = $this->staff->save(new Staff(
            null,
            Name::fromInput($name),
            Color::fromInput('#112233'),
            locationId: $locationId,
            status: $status
        ));

        return (int) $staff->id;
    }
}
