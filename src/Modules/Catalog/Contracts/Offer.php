<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * A bookable variant (CatalogApi::offer()). Buffers apply to the staff
 * member and every resource (booking-engine §1).
 */
final class Offer
{
    /**
     * @param int $capacity customers booked at the same time with one staff member.
     * @param ?int $slotStepMin null uses the site setting.
     * @param list<StaffOffer> $staff active staff who serve this variant; may be empty.
     * @param list<ResourceNeed> $resources
     * @param list<ExtraOffer> $extras the active extras the customer may add, by id.
     */
    public function __construct(
        public readonly int $serviceId,
        public readonly int $variantId,
        public readonly int $capacity,
        public readonly int $bufferBeforeMin,
        public readonly int $bufferAfterMin,
        public readonly ?int $slotStepMin,
        public readonly array $staff,
        public readonly array $resources,
        public readonly array $extras = [],
    ) {
    }
}
