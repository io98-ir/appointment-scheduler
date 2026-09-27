<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Domain\Field;

/**
 * The shapes a custom field can take (data-model §2, `fields.type`).
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Checkbox = 'checkbox';
}
