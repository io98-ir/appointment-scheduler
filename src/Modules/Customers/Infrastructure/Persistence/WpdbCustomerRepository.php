<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\DbException;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerRepository;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\Email;
use Vaqtyar\Shared\Domain\LocalDate;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\PhoneNumber;
use Vaqtyar\Shared\Domain\SearchText;
use Vaqtyar\Shared\Domain\Ulid;

final class WpdbCustomerRepository implements CustomerRepository
{
    /** MySQL ER_DUP_ENTRY. */
    private const DUPLICATE_KEY = 1062;

    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    public function find(int $id): ?Customer
    {
        return $this->first('SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL', $id);
    }

    public function findByPhone(PhoneNumber $phone): ?Customer
    {
        return $this->first('SELECT * FROM %i WHERE phone = %s AND deleted_at IS NULL', $phone->e164);
    }

    public function findByWpUser(int $wpUserId): ?Customer
    {
        return $this->first(
            'SELECT * FROM %i WHERE wp_user_id = %d AND deleted_at IS NULL ORDER BY id LIMIT 1',
            $wpUserId
        );
    }

    /**
     * @return list<Customer>
     */
    public function search(string $query, ?CustomerStatus $status, int $offset, int $limit): array
    {
        [$where, $args] = self::where($query, $status);
        $rows = $this->db->getResults(
            'SELECT * FROM %i WHERE ' . $where . ' ORDER BY id DESC LIMIT %d OFFSET %d',
            $this->table(),
            ...[...$args, $limit, $offset]
        );

        return \array_map(static fn (array $row): Customer => self::fromRow(new Row($row)), $rows);
    }

    public function count(string $query, ?CustomerStatus $status): int
    {
        [$where, $args] = self::where($query, $status);

        return (int) $this->db->getVar('SELECT COUNT(*) FROM %i WHERE ' . $where, $this->table(), ...$args);
    }

    public function save(Customer $customer): Customer
    {
        $now = $this->now();
        $columns = [
            'wp_user_id' => $customer->wpUserId,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'search_name' => SearchText::normalize($customer->fullName()),
            'phone' => $customer->phone->e164,
            'email' => $customer->email?->value,
            'birth_date' => $customer->birthDate?->toString(),
            'note' => $customer->note,
            'tags' => \json_encode($customer->tags, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            'status' => $customer->status->value,
            'updated_at' => $now,
        ];
        $id = $customer->id;
        try {
            if (null === $id) {
                $uuid = $customer->uuid ?? Ulid::fromParts($this->clock->now(), \random_bytes(10));
                $id = $this->db->insert(
                    $this->table(),
                    $columns + ['uuid' => $uuid->toString(), 'created_at' => $now]
                );
            } else {
                $this->db->update($this->table(), $columns, ['id' => $id, 'deleted_at' => null]);
            }
        } catch (DbException $e) {
            if (self::DUPLICATE_KEY === $e->errno) {
                throw new Conflict('phone_taken', 'Another customer has this phone number.');
            }
            throw $e;
        }

        // Null after an update: deleted since the caller found it.
        return $this->find($id) ?? throw new NotFound('customer_not_found', 'No customer has this id.');
    }

    public function delete(int $id): void
    {
        $now = $this->now();
        $this->db->update(
            $this->table(),
            ['phone' => null, 'deleted_at' => $now, 'updated_at' => $now],
            ['id' => $id, 'deleted_at' => null]
        );
    }

    public function unlinkWpUser(int $wpUserId): void
    {
        $this->db->update($this->table(), ['wp_user_id' => null], ['wp_user_id' => $wpUserId]);
    }

    /**
     * The WHERE clause of a search. The name and the email match anywhere;
     * the phone too when the query is a number, without its leading zeros,
     * so "0912 123" finds +98912123….
     *
     * @param string $query Already normalized (SearchText).
     * @return array{literal-string, list<string>} The clause and its arguments.
     */
    public static function where(string $query, ?CustomerStatus $status): array
    {
        [$where, $args] = self::searchClause($query);
        if (null === $status) {
            return [$where, $args];
        }

        return [$where . ' AND status = %s', [...$args, $status->value]];
    }

    /**
     * @return array{literal-string, list<string>}
     */
    private static function searchClause(string $query): array
    {
        if ('' === $query) {
            return ['deleted_at IS NULL', []];
        }
        $like = '%' . \addcslashes($query, '\\%_') . '%';
        $digits = 1 === \preg_match('/^[+\d\s\-]+$/D', $query)
            ? \ltrim((string) \preg_replace('/\D/', '', $query), '0')
            : '';
        if ('' === $digits) {
            return ['deleted_at IS NULL AND (search_name LIKE %s OR email LIKE %s)', [$like, $like]];
        }

        return [
            'deleted_at IS NULL AND (search_name LIKE %s OR email LIKE %s OR phone LIKE %s)',
            [$like, $like, '%' . $digits . '%'],
        ];
    }

    /**
     * @param literal-string $sql
     */
    private function first(string $sql, int|string $arg): ?Customer
    {
        $rows = $this->db->getResults($sql, $this->table(), $arg);

        return [] === $rows ? null : self::fromRow(new Row($rows[0]));
    }

    private static function fromRow(Row $row): Customer
    {
        $email = $row->stringOrNull('email');
        $birthDate = $row->stringOrNull('birth_date');
        $tags = \json_decode($row->string('tags'), true);

        return new Customer(
            $row->int('id'),
            Ulid::fromString($row->string('uuid')),
            $row->string('first_name'),
            $row->string('last_name'),
            PhoneNumber::fromInput($row->string('phone')),
            null === $email ? null : Email::fromInput($email),
            $row->intOrNull('wp_user_id'),
            null === $birthDate ? null : LocalDate::fromString($birthDate),
            $row->string('note'),
            \is_array($tags) ? \array_values(\array_filter($tags, \is_string(...))) : [],
            CustomerStatus::from($row->string('status')),
        );
    }

    /**
     * The full name, read on every call: switch_to_blog() changes the prefix.
     */
    private function table(): string
    {
        return Tables::name('customers');
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
