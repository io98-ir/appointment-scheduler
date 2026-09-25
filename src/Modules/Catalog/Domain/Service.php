<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A bookable service with its variants, the staff who serve it (with their
 * own price or duration) and the resource groups it needs. One aggregate:
 * it is saved as a whole, so its rules hold across all of them.
 */
final class Service
{
    use GuardsStoredNumbers;

    public const MAX_CAPACITY = 1000;

    /** Bytes, the TEXT column. */
    public const MAX_DESCRIPTION_LENGTH = 65535;

    /**
     * @param ?int $id null until stored.
     * @param list<Variant> $variants at least one, exactly one of them the default.
     * @param list<ServiceStaff> $staff at most one assignment per staff member and variant.
     * @param list<ResourceRequirement> $resources at most one per group.
     * @param ?int $imageId a media library attachment.
     * @param int $capacity customers booked at the same time with one staff member (group booking).
     */
    public function __construct(
        public readonly ?int $id,
        public readonly Name $name,
        public readonly array $variants,
        public readonly array $staff = [],
        public readonly array $resources = [],
        public readonly ?int $categoryId = null,
        public readonly string $description = '',
        public readonly ?int $imageId = null,
        public readonly int $capacity = 1,
        public readonly Status $status = Status::Active,
        public readonly int $sort = 0,
    ) {
        self::assertIds($id, $categoryId, $imageId);
        self::assertSort($sort);
        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw new InvalidValue('invalid_capacity', 'A service takes 1 to 1000 customers at a time.');
        }
        if (\strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new InvalidValue('text_too_long', 'The description is too long.');
        }
        $this->assertVariants();
        $this->assertStaff();
        $this->assertResources();
    }

    public function defaultVariant(): Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->isDefault) {
                return $variant;
            }
        }

        throw new \LogicException('The constructor guarantees a default variant.');
    }

    /**
     * The duration and price of a stored variant with a staff member, or null
     * when they do not serve it. Each field comes from the first that sets it:
     * the assignment for this variant, the service-wide assignment (which only
     * sets fields when there is a single variant), the variant.
     *
     * @throws \InvalidArgumentException when the variant is not one of this service's.
     */
    public function terms(int $variantId, int $staffId): ?Terms
    {
        $variant = $this->variant($variantId);
        $specific = $this->assignment($staffId, $variantId);
        $general = $this->assignment($staffId, null);
        if (null === $specific && null === $general) {
            return null;
        }

        return new Terms(
            $specific->durationMin ?? $general->durationMin ?? $variant->durationMin,
            $specific->price ?? $general->price ?? $variant->price,
        );
    }

    private function variant(int $id): Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->id === $id) {
                return $variant;
            }
        }

        throw new \InvalidArgumentException('The variant is not one of this service\'s.');
    }

    private function assignment(int $staffId, ?int $variantId): ?ServiceStaff
    {
        foreach ($this->staff as $assignment) {
            if ($assignment->staffId === $staffId && $assignment->variantId === $variantId) {
                return $assignment;
            }
        }

        return null;
    }

    private function assertVariants(): void
    {
        if ([] === $this->variants) {
            throw new InvalidValue('no_variant', 'A service needs at least one variant.');
        }
        $defaults = \count(\array_filter($this->variants, static fn (Variant $v) => $v->isDefault));
        if (1 !== $defaults) {
            throw new InvalidValue('default_variant', 'Exactly one variant must be the default.');
        }
        $ids = \array_filter(
            \array_map(static fn (Variant $v) => $v->id, $this->variants),
            static fn (?int $id) => null !== $id
        );
        if (\count($ids) !== \count(\array_unique($ids))) {
            throw new InvalidValue('duplicate_variant', 'A variant is listed twice.');
        }
    }

    private function assertStaff(): void
    {
        $variantIds = \array_map(static fn (Variant $v) => $v->id, $this->variants);
        $seen = [];
        foreach ($this->staff as $assignment) {
            // A new variant has no id yet, so an assignment can only name a stored one.
            if (null !== $assignment->variantId && !\in_array($assignment->variantId, $variantIds, true)) {
                throw new InvalidValue('unknown_variant', 'A staff assignment names a variant of another service.');
            }
            // One price or duration for variants that differ in both would erase the difference.
            $overrides = null !== $assignment->price || null !== $assignment->durationMin;
            if (null === $assignment->variantId && $overrides && \count($this->variants) > 1) {
                throw new InvalidValue('override_needs_variant', 'With several variants, overrides are per variant.');
            }
            $key = $assignment->staffId . ':' . ($assignment->variantId ?? '*');
            if (isset($seen[$key])) {
                throw new InvalidValue('duplicate_staff', 'A staff member is assigned to the same variant twice.');
            }
            $seen[$key] = true;
        }
    }

    private function assertResources(): void
    {
        $groups = \array_map(static fn (ResourceRequirement $r) => $r->groupKey->value, $this->resources);
        if (\count($groups) !== \count(\array_unique($groups))) {
            throw new InvalidValue('duplicate_resource_group', 'A resource group is asked for twice.');
        }
    }
}
