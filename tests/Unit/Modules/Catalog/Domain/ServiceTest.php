<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Catalog\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Catalog\Domain\ResourceRequirement;
use Vaqtyar\Modules\Catalog\Domain\Service;
use Vaqtyar\Modules\Catalog\Domain\ServiceStaff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Modules\Catalog\Domain\Terms;
use Vaqtyar\Modules\Catalog\Domain\Variant;
use Vaqtyar\Shared\Domain\Money;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\Slug;

final class ServiceTest extends TestCase
{
    use AssertsInvalidValue;

    private const SHORT = 11;
    private const LONG = 12;
    private const DR_KARIMI = 3;
    private const DR_AHMADI = 4;
    private const INTERN = 5;

    public function testAServiceWithOneDefaultVariantIsValid(): void
    {
        $service = self::service([self::variant(null, 30, 1_000_000, isDefault: true)]);
        $default = $service->defaultVariant();

        self::assertSame(
            [1, Status::Active, 30, 1_000_000],
            [$service->capacity, $service->status, $default->durationMin, $default->price->amount]
        );
    }

    public function testAServiceNeedsAVariant(): void
    {
        self::assertInvalid('no_variant', static fn () => self::service([]));
    }

    /**
     * @return iterable<string, array{list<bool>}>
     */
    public static function wrongDefaults(): iterable
    {
        yield 'none' => [[false, false]];
        yield 'two' => [[true, true]];
    }

    /**
     * @dataProvider wrongDefaults
     * @param list<bool> $defaults
     */
    public function testExactlyOneVariantIsTheDefault(array $defaults): void
    {
        $variants = \array_map(static fn (bool $isDefault) => self::variant(null, 30, 0, $isDefault), $defaults);

        self::assertInvalid('default_variant', static fn () => self::service($variants));
    }

    public function testVariantIdsAreUnique(): void
    {
        self::assertInvalid('duplicate_variant', static fn () => self::service([
            self::variant(self::SHORT, 30, 0, isDefault: true),
            self::variant(self::SHORT, 60, 0),
        ]));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidCapacities(): iterable
    {
        yield 'zero' => [0];
        yield 'over the limit' => [Service::MAX_CAPACITY + 1];
    }

    /**
     * @dataProvider invalidCapacities
     */
    public function testAServiceTakesAtLeastOneCustomerAtATime(int $capacity): void
    {
        self::assertInvalid('invalid_capacity', static fn () => self::service(
            [self::variant(null, 30, 0, isDefault: true)],
            capacity: $capacity,
        ));
    }

    /**
     * @return iterable<string, array{int, int, int, ?int, string}>
     */
    public static function invalidVariants(): iterable
    {
        yield 'zero duration' => [0, 0, 0, null, 'invalid_duration'];
        yield 'longer than a day' => [1441, 0, 0, null, 'invalid_duration'];
        yield 'negative buffer before' => [30, -1, 0, null, 'invalid_buffer'];
        yield 'buffer after longer than a day' => [30, 0, 1441, null, 'invalid_buffer'];
        yield 'zero slot step' => [30, 0, 0, 0, 'invalid_slot_step'];
    }

    /**
     * @dataProvider invalidVariants
     */
    public function testAVariantGuardsItsTimes(int $duration, int $before, int $after, ?int $step, string $code): void
    {
        self::assertInvalid($code, static fn () => new Variant(
            id: null,
            label: '',
            durationMin: $duration,
            price: Money::zero(),
            isDefault: true,
            bufferBeforeMin: $before,
            bufferAfterMin: $after,
            slotStepMin: $step,
        ));
    }

    public function testAVariantPriceCannotBeNegative(): void
    {
        self::assertInvalid('invalid_price', static fn () => self::variant(null, 30, -1, isDefault: true));
    }

    public function testAVariantLabelHasALimit(): void
    {
        self::assertInvalid('text_too_long', static fn () => new Variant(
            id: null,
            label: \str_repeat('a', Variant::MAX_LABEL_LENGTH + 1),
            durationMin: 30,
            price: Money::zero(),
            isDefault: true,
        ));
    }

    public function testStaffWithoutAnOverrideGetTheVariantTerms(): void
    {
        $service = self::clinic([new ServiceStaff(self::DR_AHMADI)]);

        self::assertEquals(new Terms(30, Money::ofRial(1_000_000)), $service->terms(self::SHORT, self::DR_AHMADI));
        self::assertEquals(new Terms(60, Money::ofRial(1_800_000)), $service->terms(self::LONG, self::DR_AHMADI));
    }

    public function testAServiceWideOverrideAppliesToTheOnlyVariant(): void
    {
        $service = self::service(
            [self::variant(self::SHORT, 30, 1_000_000, isDefault: true)],
            staff: [new ServiceStaff(self::DR_KARIMI, price: Money::ofRial(2_500_000))],
        );

        self::assertEquals(new Terms(30, Money::ofRial(2_500_000)), $service->terms(self::SHORT, self::DR_KARIMI));
    }

    /**
     * One price for a 30 and a 60 minute visit, or one duration for both,
     * would erase the difference the variants exist for.
     */
    public function testWithSeveralVariantsAnOverrideNamesItsVariant(): void
    {
        self::assertInvalid(
            'override_needs_variant',
            static fn () => self::clinic([new ServiceStaff(self::DR_KARIMI, price: Money::ofRial(2_500_000))])
        );
        self::assertInvalid(
            'override_needs_variant',
            static fn () => self::clinic([new ServiceStaff(self::DR_KARIMI, durationMin: 40)])
        );
    }

    /**
     * Field by field: the variant row, then the service-wide row, then the variant.
     */
    public function testAVariantOverrideWinsOverTheServiceWideOneFieldByField(): void
    {
        $service = self::service(
            [self::variant(self::SHORT, 30, 1_000_000, isDefault: true)],
            staff: [
                new ServiceStaff(self::DR_KARIMI, price: Money::ofRial(2_500_000), durationMin: 40),
                new ServiceStaff(self::DR_KARIMI, variantId: self::SHORT, durationMin: 75),
            ],
        );

        self::assertEquals(new Terms(75, Money::ofRial(2_500_000)), $service->terms(self::SHORT, self::DR_KARIMI));
    }

    public function testWithSeveralVariantsEachHasItsOwnOverride(): void
    {
        $service = self::clinic([
            new ServiceStaff(self::DR_KARIMI),
            new ServiceStaff(self::DR_KARIMI, variantId: self::LONG, price: Money::ofRial(2_500_000), durationMin: 75),
        ]);

        self::assertEquals(new Terms(30, Money::ofRial(1_000_000)), $service->terms(self::SHORT, self::DR_KARIMI));
        self::assertEquals(new Terms(75, Money::ofRial(2_500_000)), $service->terms(self::LONG, self::DR_KARIMI));
    }

    public function testStaffAssignedToOneVariantOnlyDoNotOfferTheOthers(): void
    {
        $service = self::clinic([
            new ServiceStaff(self::INTERN, variantId: self::SHORT, price: Money::ofRial(600_000)),
        ]);

        self::assertEquals(new Terms(30, Money::ofRial(600_000)), $service->terms(self::SHORT, self::INTERN));
        self::assertNull($service->terms(self::LONG, self::INTERN));
    }

    public function testStaffNotAssignedHaveNoTerms(): void
    {
        self::assertNull(self::clinic([new ServiceStaff(self::DR_AHMADI)])->terms(self::SHORT, self::DR_KARIMI));
    }

    public function testTermsForAVariantOfAnotherServiceIsAProgrammingError(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::clinic([new ServiceStaff(self::DR_AHMADI)])->terms(999, self::DR_AHMADI);
    }

    public function testTheSameStaffAndVariantCanBeAssignedOnlyOnce(): void
    {
        self::assertInvalid('duplicate_staff', static fn () => self::clinic([
            new ServiceStaff(self::DR_KARIMI, variantId: self::LONG),
            new ServiceStaff(self::DR_KARIMI, variantId: self::LONG, durationMin: 90),
        ]));
        self::assertInvalid('duplicate_staff', static fn () => self::clinic([
            new ServiceStaff(self::DR_KARIMI),
            new ServiceStaff(self::DR_KARIMI),
        ]));
    }

    public function testAnOverrideMustPointToAVariantOfThisService(): void
    {
        self::assertInvalid(
            'unknown_variant',
            static fn () => self::clinic([new ServiceStaff(self::DR_KARIMI, variantId: 999)])
        );
    }

    /**
     * @return iterable<string, array{?int, ?int, string}>
     */
    public static function invalidOverrides(): iterable
    {
        yield 'negative price' => [-1, null, 'invalid_price'];
        yield 'zero duration' => [null, 0, 'invalid_duration'];
        yield 'longer than a day' => [null, 1441, 'invalid_duration'];
    }

    /**
     * @dataProvider invalidOverrides
     */
    public function testAnOverrideGuardsItsPriceAndDuration(?int $price, ?int $duration, string $code): void
    {
        self::assertInvalid($code, static fn () => new ServiceStaff(
            self::DR_KARIMI,
            price: null === $price ? null : Money::ofRial($price),
            durationMin: $duration,
        ));
    }

    public function testAServiceAsksForResourcesByGroup(): void
    {
        $service = self::service(
            [self::variant(null, 30, 0, isDefault: true)],
            resources: [self::needs('room'), self::needs('laser', 2)],
        );

        self::assertSame(
            [['room', 1], ['laser', 2]],
            \array_map(static fn (ResourceRequirement $r) => [$r->groupKey->value, $r->quantity], $service->resources)
        );
    }

    public function testAResourceGroupIsAskedForOnce(): void
    {
        self::assertInvalid('duplicate_resource_group', static fn () => self::service(
            [self::variant(null, 30, 0, isDefault: true)],
            resources: [self::needs('room'), self::needs('room', 2)],
        ));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'over the limit' => [ResourceRequirement::MAX_QUANTITY + 1];
    }

    /**
     * @dataProvider invalidQuantities
     */
    public function testAResourceRequirementNeedsAtLeastOne(int $quantity): void
    {
        self::assertInvalid('invalid_quantity', static fn () => self::needs('room', $quantity));
    }

    public function testADescriptionHasALimitInBytes(): void
    {
        self::assertInvalid('text_too_long', static fn () => self::service(
            [self::variant(null, 30, 0, isDefault: true)],
            description: \str_repeat('a', 65536),
        ));
    }

    /**
     * Visit: 30 or 60 minutes, for 1,000,000 or 1,800,000 rials.
     *
     * @param list<ServiceStaff> $staff
     */
    private static function clinic(array $staff): Service
    {
        return self::service(
            [self::variant(self::SHORT, 30, 1_000_000, isDefault: true), self::variant(self::LONG, 60, 1_800_000)],
            staff: $staff,
        );
    }

    /**
     * @param list<Variant> $variants
     * @param list<ServiceStaff> $staff
     * @param list<ResourceRequirement> $resources
     */
    private static function service(
        array $variants,
        array $staff = [],
        array $resources = [],
        int $capacity = 1,
        string $description = '',
    ): Service {
        return new Service(
            id: 7,
            name: Name::fromInput('ویزیت'),
            variants: $variants,
            staff: $staff,
            resources: $resources,
            description: $description,
            capacity: $capacity,
        );
    }

    private static function variant(?int $id, int $duration, int $price, bool $isDefault = false): Variant
    {
        return new Variant(
            id: $id,
            label: '',
            durationMin: $duration,
            price: Money::ofRial($price),
            isDefault: $isDefault,
        );
    }

    private static function needs(string $group, int $quantity = 1): ResourceRequirement
    {
        return new ResourceRequirement(Slug::fromInput($group), $quantity);
    }
}
