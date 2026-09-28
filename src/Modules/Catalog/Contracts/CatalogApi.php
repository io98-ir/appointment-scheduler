<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Contracts;

/**
 * What other modules may know of the catalog: what can be booked, who and
 * what serves it, and where (architecture §3). Only bookable items come
 * back: a deleted or inactive service, variant, staff member, resource or
 * location is left out, as if it did not exist, and so are the staff and
 * resources of an inactive location.
 *
 * No capability is checked: the callers are use cases (availability,
 * holds) that do their own authorization.
 */
interface CatalogApi
{
    /**
     * A variant as booked: its service's rules, the staff who serve it with
     * their own duration and price, and the resources it needs. Null when
     * the variant or its service cannot be booked.
     */
    public function offer(int $variantId): ?Offer;

    /**
     * Null when the location is deleted or inactive.
     */
    public function location(int $locationId): ?LocationInfo;

    /**
     * Whether a staff member, resource or location with this id exists and
     * is not deleted, active or not: e.g. to edit the schedule of someone
     * on leave.
     *
     * @param string $kind "staff", "resource" or "location"; anything else is not stored.
     */
    public function isStored(string $kind, int $id): bool;
}
