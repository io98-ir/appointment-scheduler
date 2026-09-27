<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Field\Field;

/**
 * The fields table (data-model §2): a service's custom fields are its own
 * plus every global one, unlike a policy, additive rather than one level
 * winning over another.
 */
interface FieldReader
{
    /**
     * @return list<Field> sorted the way they are shown, so a later
     *     field's show_if can rely on an earlier one's answer.
     */
    public function forService(int $serviceId): array;
}
