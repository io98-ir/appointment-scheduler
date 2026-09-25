<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * One duration and price of a service; several variants are the "custom
 * duration" feature. Part of the Service aggregate.
 */
final class Variant
{
    use GuardsStoredNumbers;

    /** A day: the longest duration, buffer or slot step. */
    public const MAX_MINUTES = 1440;

    /** Characters, the VARCHAR(191) column. */
    public const MAX_LABEL_LENGTH = 191;

    /**
     * @param ?int $id null until stored.
     * @param string $label may be empty for a service with a single variant.
     * @param bool $isDefault the variant preselected in the widget; exactly one per service.
     * @param int $bufferBeforeMin kept free before the appointment, on the staff and every resource.
     * @param ?int $slotStepMin the gap between offered start times; null uses the site setting.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $label,
        public readonly int $durationMin,
        public readonly Money $price,
        public readonly bool $isDefault,
        public readonly int $bufferBeforeMin = 0,
        public readonly int $bufferAfterMin = 0,
        public readonly ?int $slotStepMin = null,
        public readonly int $sort = 0,
    ) {
        self::assertIds($id);
        self::assertSort($sort);
        if (\mb_strlen($label) > self::MAX_LABEL_LENGTH) {
            throw new InvalidValue('text_too_long', 'The label is too long.');
        }
        if ($durationMin < 1 || $durationMin > self::MAX_MINUTES) {
            throw new InvalidValue('invalid_duration', 'A duration is 1 to 1440 minutes.');
        }
        if ($price->isNegative()) {
            throw new InvalidValue('invalid_price', 'A price cannot be negative.');
        }
        foreach ([$bufferBeforeMin, $bufferAfterMin] as $buffer) {
            if ($buffer < 0 || $buffer > self::MAX_MINUTES) {
                throw new InvalidValue('invalid_buffer', 'A buffer is 0 to 1440 minutes.');
            }
        }
        if (null !== $slotStepMin && ($slotStepMin < 1 || $slotStepMin > self::MAX_MINUTES)) {
            throw new InvalidValue('invalid_slot_step', 'A slot step is 1 to 1440 minutes.');
        }
    }
}
