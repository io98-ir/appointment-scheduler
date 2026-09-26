<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Application;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Application\CatalogService;
use Vaqtyar\Modules\Catalog\Domain\BookableResource;
use Vaqtyar\Modules\Catalog\Domain\BookableResourceRepository;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Extra;
use Vaqtyar\Modules\Catalog\Domain\ExtraRepository;
use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\LocationRepository;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategory;
use Vaqtyar\Modules\Catalog\Domain\ServiceCategoryRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceRepository;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\Slug;
use Vaqtyar\Tests\Unit\Modules\Catalog\Domain\AssertsInvalidValue;

/**
 * The use cases behind the admin catalog API: authorization, existence and
 * the references between aggregates. Storage is the repositories' (the
 * integration suite).
 */
final class CatalogServiceTest extends TestCase
{
    use AssertsInvalidValue;

    private bool $allowed = true;

    private LocationRepository&MockInterface $locations;

    private StaffRepository&MockInterface $staff;

    private BookableResourceRepository&MockInterface $resources;

    private ServiceCategoryRepository&MockInterface $categories;

    private ServiceRepository&MockInterface $services;

    private ExtraRepository&MockInterface $extras;

    private CatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locations = self::mock(LocationRepository::class);
        $this->staff = self::mock(StaffRepository::class);
        $this->resources = self::mock(BookableResourceRepository::class);
        $this->categories = self::mock(ServiceCategoryRepository::class);
        $this->services = self::mock(ServiceRepository::class);
        $this->extras = self::mock(ExtraRepository::class);
        $authorizer = new class (fn (): bool => $this->allowed) implements Authorizer {
            /**
             * @param \Closure(): bool $allowed
             */
            public function __construct(private readonly \Closure $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return ($this->allowed)() && 'manage_catalog' === $capability;
            }
        };
        $this->catalog = new CatalogService(
            $authorizer,
            $this->locations,
            $this->staff,
            $this->resources,
            $this->categories,
            $this->services,
            $this->extras
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{\Closure(CatalogService): mixed}>
     */
    public static function useCases(): iterable
    {
        yield 'location' => [static fn (CatalogService $c) => $c->location(1)];
        yield 'locations' => [static fn (CatalogService $c) => $c->locations(0, 20)];
        yield 'save location' => [static fn (CatalogService $c) => $c->saveLocation(self::aLocation(null))];
        yield 'delete location' => [static fn (CatalogService $c) => $c->deleteLocation(1)];
        yield 'staff member' => [static fn (CatalogService $c) => $c->staffMember(1)];
        yield 'staff' => [static fn (CatalogService $c) => $c->staff(0, 20)];
        yield 'save staff' => [static fn (CatalogService $c) => $c->saveStaff(self::aStaffMember(null))];
        yield 'delete staff' => [static fn (CatalogService $c) => $c->deleteStaff(1)];
        yield 'resource' => [static fn (CatalogService $c) => $c->resource(1)];
        yield 'resources' => [static fn (CatalogService $c) => $c->resources(0, 20)];
        yield 'save resource' => [static fn (CatalogService $c) => $c->saveResource(self::aResource(null))];
        yield 'delete resource' => [static fn (CatalogService $c) => $c->deleteResource(1)];
        yield 'category' => [static fn (CatalogService $c) => $c->category(1)];
        yield 'categories' => [static fn (CatalogService $c) => $c->categories(0, 20)];
        yield 'save category' => [static fn (CatalogService $c) => $c->saveCategory(self::aCategory(null))];
        yield 'delete category' => [static fn (CatalogService $c) => $c->deleteCategory(1)];
        yield 'service' => [static fn (CatalogService $c) => $c->service(1)];
        yield 'services' => [static fn (CatalogService $c) => $c->services(0, 20)];
        yield 'save service' => [static fn (CatalogService $c) => $c->saveService(self::aService(null))];
        yield 'delete service' => [static fn (CatalogService $c) => $c->deleteService(1)];
        yield 'extra' => [static fn (CatalogService $c) => $c->extra(1)];
        yield 'extras' => [static fn (CatalogService $c) => $c->extras(0, 20)];
        yield 'save extra' => [static fn (CatalogService $c) => $c->saveExtra(self::anExtra(null))];
        yield 'delete extra' => [static fn (CatalogService $c) => $c->deleteExtra(1)];
    }

    /**
     * @dataProvider useCases
     * @param \Closure(CatalogService): mixed $useCase
     */
    public function testEveryUseCaseNeedsTheCapability(\Closure $useCase): void
    {
        $this->allowed = false;

        // The mocks expect nothing, so any repository call fails the test too.
        $this->expectException(Forbidden::class);
        $useCase($this->catalog);
    }

    public function testAMissingItemIsNotFound(): void
    {
        $this->locations->shouldReceive('find')->with(9)->andReturnNull();

        try {
            $this->catalog->location(9);
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('location_not_found', $e->errorCode);
        }
    }

    public function testAPageHasItsItemsAndTheTotal(): void
    {
        $this->categories->shouldReceive('page')->with(20, 10)->andReturn([self::aCategory(21)]);
        $this->categories->shouldReceive('count')->andReturn(21);

        $page = $this->catalog->categories(20, 10);

        self::assertSame([21, 21], [$page->items[0]->id, $page->total]);
    }

    public function testANewItemIsSaved(): void
    {
        $stored = self::aLocation(1);
        $this->locations->shouldReceive('save')->once()->andReturn($stored);

        self::assertSame($stored, $this->catalog->saveLocation(self::aLocation(null)));
    }

    public function testUpdatingAMissingItemIsNotFound(): void
    {
        $this->categories->shouldReceive('find')->with(4)->andReturnNull();
        $this->categories->shouldNotReceive('save');

        $this->expectException(NotFound::class);
        $this->catalog->saveCategory(self::aCategory(4));
    }

    public function testAnExistingItemIsUpdated(): void
    {
        $this->categories->shouldReceive('find')->with(4)->andReturn(self::aCategory(4));
        $this->categories->shouldReceive('save')->once()->andReturnArg(0);

        self::assertSame(4, $this->catalog->saveCategory(self::aCategory(4))->id);
    }

    public function testDeletingAMissingItemIsNotFound(): void
    {
        $this->extras->shouldReceive('find')->with(4)->andReturnNull();
        $this->extras->shouldNotReceive('delete');

        $this->expectException(NotFound::class);
        $this->catalog->deleteExtra(4);
    }

    public function testAnItemIsDeleted(): void
    {
        $this->extras->shouldReceive('find')->with(4)->andReturn(self::anExtra(4));
        $this->extras->shouldReceive('delete')->once()->with(4);

        $this->catalog->deleteExtra(4);
        $this->addToAssertionCount(1);
    }

    /**
     * Its extras would name a service no save accepts.
     */
    public function testADeletedServiceTakesItsExtrasWithIt(): void
    {
        $this->services->shouldReceive('find')->with(6)->andReturn(self::aService(6));
        $this->services->shouldReceive('delete')->once()->with(6);
        $this->extras->shouldReceive('deleteOfService')->once()->with(6);

        $this->catalog->deleteService(6);
        $this->addToAssertionCount(1);
    }

    /**
     * Staff and resources at a deleted location would lose the timezone
     * their working hours are reckoned in.
     */
    public function testALocationInUseIsNotDeleted(): void
    {
        $this->locations->shouldReceive('find')->with(1)->andReturn(self::aLocation(1));
        $this->locations->shouldReceive('isReferenced')->with(1)->andReturnTrue();
        $this->locations->shouldNotReceive('delete');

        self::assertInvalid('location_in_use', fn () => $this->catalog->deleteLocation(1));
    }

    public function testStaffAtAnUnknownLocationIsRefused(): void
    {
        $this->locations->shouldReceive('find')->with(7)->andReturnNull();
        $this->staff->shouldNotReceive('save');

        self::assertInvalid('unknown_location', fn () => $this->catalog->saveStaff(self::aStaffMember(null, 7)));
    }

    public function testStaffAtAKnownLocationIsSaved(): void
    {
        $this->locations->shouldReceive('find')->with(7)->andReturn(self::aLocation(7));
        $this->staff->shouldReceive('save')->once()->andReturnArg(0);

        self::assertSame(7, $this->catalog->saveStaff(self::aStaffMember(null, 7))->locationId);
    }

    public function testAResourceAtAnUnknownLocationIsRefused(): void
    {
        $this->locations->shouldReceive('find')->with(7)->andReturnNull();

        self::assertInvalid('unknown_location', fn () => $this->catalog->saveResource(self::aResource(null, 7)));
    }

    public function testAServiceInAnUnknownCategoryIsRefused(): void
    {
        $this->categories->shouldReceive('find')->with(8)->andReturnNull();

        self::assertInvalid(
            'unknown_category',
            fn () => $this->catalog->saveService(self::aService(null, categoryId: 8))
        );
    }

    public function testAServiceWithUnknownStaffIsRefused(): void
    {
        $this->staff->shouldReceive('find')->with(3)->andReturn(self::aStaffMember(3));
        $this->staff->shouldReceive('find')->with(4)->andReturnNull();

        self::assertInvalid(
            'unknown_staff',
            fn () => $this->catalog->saveService(self::aService(null, staffIds: [3, 4]))
        );
    }

    /**
     * A variant id in a new service would take over another service's variant.
     */
    public function testANewServiceCannotNameAStoredVariant(): void
    {
        $this->services->shouldNotReceive('save');

        self::assertInvalid(
            'unknown_variant',
            fn () => $this->catalog->saveService(self::aService(null, variantId: 50))
        );
    }

    public function testAnUpdatedServiceKeepsToItsOwnVariants(): void
    {
        $this->services->shouldReceive('find')->with(5)->andReturn(self::aService(5, variantId: 50));
        $this->services->shouldNotReceive('save');

        self::assertInvalid(
            'unknown_variant',
            fn () => $this->catalog->saveService(self::aService(5, variantId: 51))
        );
    }

    public function testAServiceWithKnownReferencesIsSaved(): void
    {
        $this->categories->shouldReceive('find')->with(8)->andReturn(self::aCategory(8));
        $this->staff->shouldReceive('find')->with(3)->andReturn(self::aStaffMember(3));
        $this->services->shouldReceive('find')->with(5)->andReturn(self::aService(5, variantId: 50));
        $this->services->shouldReceive('save')->once()->andReturnArg(0);

        $saved = $this->catalog->saveService(self::aService(5, categoryId: 8, staffIds: [3, 3], variantId: 50));

        self::assertSame(5, $saved->id);
    }

    public function testAnExtraOfAnUnknownServiceIsRefused(): void
    {
        $this->services->shouldReceive('find')->with(6)->andReturnNull();

        self::assertInvalid('unknown_service', fn () => $this->catalog->saveExtra(self::anExtra(null, 6)));
    }

    public function testAnExtraOfAKnownServiceIsSaved(): void
    {
        $this->services->shouldReceive('find')->with(6)->andReturn(self::aService(6));
        $this->extras->shouldReceive('save')->once()->andReturnArg(0);

        self::assertSame(6, $this->catalog->saveExtra(self::anExtra(null, 6))->serviceId);
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

    private static function aLocation(?int $id): Location
    {
        return new Location($id, Name::fromInput('Main'), new \DateTimeZone('Asia/Tehran'));
    }

    private static function aStaffMember(?int $id, ?int $locationId = null): Staff
    {
        return new Staff($id, Name::fromInput('Dr. Karimi'), Color::fromInput('#112233'), locationId: $locationId);
    }

    private static function aResource(?int $id, ?int $locationId = null): BookableResource
    {
        return new BookableResource($id, Name::fromInput('Room 1'), Slug::fromInput('room'), $locationId);
    }

    private static function aCategory(?int $id): ServiceCategory
    {
        return new ServiceCategory($id, Name::fromInput('Dental'), Color::fromInput('#445566'));
    }

    /**
     * @param list<int> $staffIds Assigned to every variant; a repeated id is a
     *     second assignment, to one variant.
     */
    private static function aService(
        ?int $id,
        ?int $categoryId = null,
        array $staffIds = [],
        ?int $variantId = null,
    ): Service {
        $assignments = [];
        foreach ($staffIds as $index => $staffId) {
            $assignments[] = new ServiceStaff($staffId, \in_array($staffId, \array_slice($staffIds, 0, $index), true)
                ? $variantId
                : null);
        }

        return new Service(
            $id,
            Name::fromInput('Checkup'),
            [new Variant($variantId, '', 30, Money::ofRial(1_000_000), true)],
            $assignments,
            categoryId: $categoryId
        );
    }

    private static function anExtra(?int $id, ?int $serviceId = null): Extra
    {
        return new Extra($id, Name::fromInput('X-ray'), Money::ofRial(500_000), 10, $serviceId);
    }
}
