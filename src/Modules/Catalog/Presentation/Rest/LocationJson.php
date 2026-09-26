<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Location;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\Domain\Slug;

/**
 * A location in the admin API.
 */
final class LocationJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => Fields::requiredString(),
            'timezone' => Fields::requiredString(),
            'address' => Fields::text(),
            'phone' => Fields::optionalString(),
            'holiday_calendar' => Fields::optionalString(),
            'status' => Fields::status(),
            'sort' => Fields::int(0),
        ];
    }

    public function fromInput(Input $in, ?int $id): Location
    {
        $phone = $in->stringOrNull('phone');
        $calendar = $in->stringOrNull('holiday_calendar');

        return new Location(
            $id,
            Name::fromInput($in->string('name')),
            self::timezone($in->string('timezone')),
            $in->string('address', ''),
            null === $phone ? null : PhoneNumber::fromInput($phone),
            null === $calendar ? null : Slug::fromInput($calendar),
            Status::from($in->string('status', Status::Active->value)),
            $in->int('sort', 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name->value,
            'timezone' => $location->timezone->getName(),
            'address' => $location->address,
            'phone' => $location->phone?->e164,
            'holiday_calendar' => $location->holidayCalendar?->value,
            'status' => $location->status->value,
            'sort' => $location->sort,
        ];
    }

    private static function timezone(string $name): \DateTimeZone
    {
        try {
            return new \DateTimeZone($name);
        } catch (\Exception) {
            // Location refuses zones that do parse but are no region name.
            throw new InvalidValue('invalid_timezone', 'A location needs a named timezone such as Asia/Tehran.');
        }
    }
}
