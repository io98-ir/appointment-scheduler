<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * One row of the fields table (data-model §2), for the admin CRUD screen
 * (T3.5). `field` carries the shape FieldReader/AnswerValidator use at
 * booking time (key, type, label, required, options, show_if, sort); this
 * wraps it with the row's own identity and where it applies, which the
 * booking-time Field value object has no reason to know about.
 */
final class FieldDefinition
{
    public function __construct(
        public readonly ?int $id,
        public readonly FieldScope $scope,
        public readonly ?int $serviceId,
        public readonly Field $field,
    ) {
        if (FieldScope::Service === $scope && null === $serviceId) {
            throw new InvalidValue('invalid_field', 'A service field needs a service id.');
        }
        if (FieldScope::Global === $scope && null !== $serviceId) {
            throw new InvalidValue('invalid_field', 'A global field must not have a service id.');
        }
    }
}
