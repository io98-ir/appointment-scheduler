<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\Name;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * A staff member in the admin API. The WordPress account and the avatar
 * are checked here, since only WordPress knows them.
 */
final class StaffJson
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [
            'name' => Fields::requiredString(),
            'color' => Fields::requiredString(),
            'wp_user_id' => Fields::optionalId(),
            'location_id' => Fields::optionalId(),
            'title' => Fields::text(),
            'email' => Fields::optionalString(),
            'phone' => Fields::optionalString(),
            'avatar_id' => Fields::optionalId(),
            'bio' => Fields::text(),
            'status' => Fields::status(),
            'sort' => Fields::int(0),
        ];
    }

    public function fromInput(Input $in, ?int $id): Staff
    {
        $email = $in->stringOrNull('email');
        $phone = $in->stringOrNull('phone');

        return new Staff(
            $id,
            Name::fromInput($in->string('name')),
            Color::fromInput($in->string('color')),
            $in->wpIdOrNull('wp_user_id', static fn (int $id): bool => false !== \get_userdata($id), 'unknown_user'),
            $in->intOrNull('location_id'),
            $in->string('title', ''),
            null === $email ? null : Email::fromInput($email),
            null === $phone ? null : PhoneNumber::fromInput($phone),
            $in->wpIdOrNull('avatar_id', \wp_attachment_is_image(...), 'invalid_image'),
            $in->string('bio', ''),
            Status::from($in->string('status', Status::Active->value)),
            $in->int('sort', 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toJson(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name->value,
            'color' => $staff->color->value,
            'wp_user_id' => $staff->wpUserId,
            'location_id' => $staff->locationId,
            'title' => $staff->title,
            'email' => $staff->email?->value,
            'phone' => $staff->phone?->e164,
            'avatar_id' => $staff->avatarId,
            'bio' => $staff->bio,
            'status' => $staff->status->value,
            'sort' => $staff->sort,
        ];
    }
}
