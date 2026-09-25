<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Modules\Catalog\Domain\Color;
use Vaqtyar\Modules\Catalog\Domain\Name;
use Vaqtyar\Modules\Catalog\Domain\Staff;
use Vaqtyar\Modules\Catalog\Domain\StaffRepository;
use Vaqtyar\Modules\Catalog\Domain\Status;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class WpdbStaffRepository implements StaffRepository
{
    private readonly CatalogTable $table;

    public function __construct(private readonly Db $db, Clock $clock)
    {
        $this->table = new CatalogTable($db, $clock, 'staff', 'sort, id');
    }

    public function find(int $id): ?Staff
    {
        $row = $this->table->find($id);

        return null === $row ? null : self::fromRow($row);
    }

    /**
     * @return list<Staff>
     */
    public function page(int $offset, int $limit): array
    {
        return \array_map(self::fromRow(...), $this->table->page($offset, $limit));
    }

    public function count(): int
    {
        return $this->table->count();
    }

    public function save(Staff $staff): Staff
    {
        $columns = [
            'wp_user_id' => $staff->wpUserId,
            'location_id' => $staff->locationId,
            'name' => $staff->name->value,
            'search_name' => self::searchName($staff->name->value),
            'title' => $staff->title,
            'email' => $staff->email?->value,
            'phone' => $staff->phone?->e164,
            'color' => $staff->color->value,
            'avatar_id' => $staff->avatarId,
            'bio' => $staff->bio,
            'status' => $staff->status->value,
            'sort' => $staff->sort,
        ];
        $id = $staff->id;
        if (null === $id) {
            $id = $this->table->insert($columns);
        } else {
            $this->table->update($id, $columns);
        }

        return $this->find($id) ?? throw new \LogicException('The staff member just saved is gone.');
    }

    public function delete(int $id): void
    {
        $this->table->delete($id);
    }

    /**
     * @param list<int> $ids
     * @return list<Staff>
     */
    public function findMany(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        // One %d per id, so the ids still go through prepare().
        $in = \implode(',', \array_fill(0, \count($ids), '%d'));
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE id IN (' . $in . ') AND deleted_at IS NULL',
            $this->table->table(),
            ...$ids
        );

        return \array_map(static fn (array $row): Staff => self::fromRow(new Row($row)), $rows);
    }

    /**
     * The name as a search compares it: Arabic ya and kaf as Persian, no
     * diacritics or tatweel, a zero-width non-joiner as a space, Latin digits,
     * lowercase, single spaces. Customers (T2.7) need the same; the two will
     * share it then (implementation-notes §4.3).
     */
    public static function searchName(string $name): string
    {
        $name = \strtr($name, [
            "\u{064A}" => "\u{06CC}",
            "\u{0649}" => "\u{06CC}",
            "\u{0643}" => "\u{06A9}",
            "\u{200C}" => ' ',
            "\u{0640}" => '',
        ]);
        $name = (string) \preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $name);
        $name = \strtr($name, \array_combine(
            [...\mb_str_split('۰۱۲۳۴۵۶۷۸۹'), ...\mb_str_split('٠١٢٣٤٥٦٧٨٩')],
            [...\str_split('0123456789'), ...\str_split('0123456789')]
        ));

        return \trim((string) \preg_replace('/\s+/u', ' ', \mb_strtolower($name)));
    }

    private static function fromRow(Row $row): Staff
    {
        $email = $row->stringOrNull('email');
        $phone = $row->stringOrNull('phone');

        return new Staff(
            $row->int('id'),
            Name::fromInput($row->string('name')),
            Color::fromInput($row->string('color')),
            $row->intOrNull('wp_user_id'),
            $row->intOrNull('location_id'),
            $row->string('title'),
            null === $email ? null : Email::fromInput($email),
            null === $phone ? null : PhoneNumber::fromInput($phone),
            $row->intOrNull('avatar_id'),
            $row->string('bio'),
            Status::from($row->string('status')),
            $row->int('sort'),
        );
    }
}
