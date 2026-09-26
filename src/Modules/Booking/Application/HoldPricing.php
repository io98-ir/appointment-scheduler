<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\ChosenExtra;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceCalculator;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceContext;
use Vaqtyar\Modules\Booking\Domain\Pricing\PriceQuote;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Modules\Catalog\Contracts\ExtraOffer;
use Vaqtyar\Modules\Catalog\Contracts\StaffOffer;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Rounding;

/**
 * Prices a claimed slot (booking-engine §5): the assigned staff member's
 * price for the variant, the service's time rules, the extras at their
 * current prices, the party, a coupon, and the site's rounding. The quote
 * is kept with the hold, so later price changes do not touch it. A coupon's
 * use is counted when the booking is confirmed (T2.4), which checks it again.
 */
final class HoldPricing
{
    /**
     * @param int $roundingStep rials; 1 leaves the total as it is.
     */
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly PricingReader $reader,
        private readonly int $roundingStep,
        private readonly Rounding $rounding,
    ) {
    }

    /**
     * @throws InvalidValue coupon_not_found, or why the coupon cannot be used.
     */
    public function quote(
        AvailabilityQuery $query,
        Claim $claim,
        int $now,
        ?string $couponCode = null,
    ): PriceQuote {
        $offer = $this->catalog->offer($query->variantId);
        $location = $this->catalog->location($query->locationId);
        $staff = null;
        foreach (null === $offer ? [] : $offer->staff as $candidate) {
            if ($candidate->staffId === $claim->staffId) {
                $staff = $candidate;
            }
        }
        // The claim was just made from the same catalog, in the same transaction.
        if (null === $offer || null === $location || !$staff instanceof StaffOffer) {
            throw new Conflict('slot_taken', 'The service changed while it was being booked.');
        }

        $prices = [];
        foreach ($offer->extras as $extra) {
            $prices[$extra->extraId] = $extra;
        }
        $extras = [];
        foreach (\array_count_values($query->extraIds) as $id => $qty) {
            $extra = $prices[$id] ?? null;
            if ($extra instanceof ExtraOffer) {
                $extras[] = new ChosenExtra($id, $extra->price, $qty);
            }
        }

        $coupon = null;
        if (null !== $couponCode) {
            $coupon = $this->reader->coupon($couponCode)
                ?? throw new InvalidValue('coupon_not_found', 'There is no coupon with this code.');
        }

        $calculator = PriceCalculator::standard(
            $this->reader->timeRules($offer->serviceId),
            $this->roundingStep,
            $this->rounding
        );

        return $calculator->quote(new PriceContext(
            $offer->serviceId,
            $staff->price,
            $claim->start,
            $location->timezone,
            $extras,
            $query->partySize,
            $now,
            $coupon
        ));
    }
}
