<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Application;

use Vaqtyar\Modules\Customers\Application\OtpStore;
use Vaqtyar\Modules\Customers\Application\StoredOtp;

/**
 * An OtpStore in memory, for the OtpService tests.
 */
final class InMemoryOtpStore implements OtpStore
{
    /**
     * @var list<array{
     *     id: int, phone: string, hash: string, expires: int, created: int, attempts: int, used: bool
     * }>
     */
    private array $rows = [];

    public function countSince(string $phone, int $since): int
    {
        return \count(\array_filter(
            $this->rows,
            static fn (array $row): bool => $row['phone'] === $phone && $row['created'] >= $since
        ));
    }

    public function lastCreatedAt(string $phone): ?int
    {
        $times = [];
        foreach ($this->rows as $row) {
            if ($row['phone'] === $phone) {
                $times[] = $row['created'];
            }
        }

        return [] === $times ? null : \max($times);
    }

    public function add(string $phone, string $hash, int $expiresAt, int $now): void
    {
        $this->rows[] = [
            'id' => \count($this->rows) + 1,
            'phone' => $phone,
            'hash' => $hash,
            'expires' => $expiresAt,
            'created' => $now,
            'attempts' => 0,
            'used' => false,
        ];
    }

    public function latest(string $phone, int $now): ?StoredOtp
    {
        foreach (\array_reverse($this->rows) as $row) {
            if ($row['phone'] === $phone && !$row['used'] && $row['expires'] > $now) {
                return new StoredOtp($row['id'], $row['hash'], $row['attempts']);
            }
        }

        return null;
    }

    public function recordAttempt(int $id): void
    {
        ++$this->rows[$id - 1]['attempts'];
    }

    public function consume(int $id, int $now): bool
    {
        if ($this->rows[$id - 1]['used']) {
            return false;
        }
        $this->rows[$id - 1]['used'] = true;

        return true;
    }

    public function purgeBefore(int $before): void
    {
    }
}
