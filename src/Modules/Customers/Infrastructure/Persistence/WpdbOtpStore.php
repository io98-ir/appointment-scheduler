<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Customers\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Customers\Application\OtpStore;
use Vaqtyar\Modules\Customers\Application\StoredOtp;

/**
 * OtpStore on the otp_codes table. Times are UTC DATETIMEs, written and
 * compared as text made from the caller's timestamps, never from SQL NOW().
 */
final class WpdbOtpStore implements OtpStore
{
    public function __construct(private readonly Db $db)
    {
    }

    public function countSince(string $phone, int $since): int
    {
        return (int) $this->db->getVar(
            'SELECT COUNT(*) FROM %i WHERE phone = %s AND created_at >= %s',
            Tables::name('otp_codes'),
            $phone,
            self::utc($since)
        );
    }

    public function lastCreatedAt(string $phone): ?int
    {
        $rows = $this->db->getResults(
            'SELECT created_at FROM %i WHERE phone = %s ORDER BY id DESC LIMIT 1',
            Tables::name('otp_codes'),
            $phone
        );
        if ([] === $rows) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            (new Row($rows[0]))->string('created_at'),
            new \DateTimeZone('UTC')
        );

        return false === $time ? null : $time->getTimestamp();
    }

    public function add(string $phone, string $hash, int $expiresAt, int $now): void
    {
        $this->db->insert(Tables::name('otp_codes'), [
            'phone' => $phone,
            'code_hash' => $hash,
            'expires_at' => self::utc($expiresAt),
            'created_at' => self::utc($now),
        ]);
    }

    public function latest(string $phone, int $now): ?StoredOtp
    {
        $rows = $this->db->getResults(
            'SELECT id, code_hash, attempts FROM %i
            WHERE phone = %s AND consumed_at IS NULL AND expires_at > %s ORDER BY id DESC LIMIT 1',
            Tables::name('otp_codes'),
            $phone,
            self::utc($now)
        );
        if ([] === $rows) {
            return null;
        }
        $row = new Row($rows[0]);

        return new StoredOtp($row->int('id'), $row->string('code_hash'), $row->int('attempts'));
    }

    public function recordAttempt(int $id): void
    {
        $this->db->execute('UPDATE %i SET attempts = attempts + 1 WHERE id = %d', Tables::name('otp_codes'), $id);
    }

    public function consume(int $id, int $now): bool
    {
        return 1 === $this->db->execute(
            'UPDATE %i SET consumed_at = %s WHERE id = %d AND consumed_at IS NULL',
            Tables::name('otp_codes'),
            self::utc($now),
            $id
        );
    }

    public function purgeBefore(int $before): void
    {
        $this->db->execute('DELETE FROM %i WHERE created_at < %s', Tables::name('otp_codes'), self::utc($before));
    }

    private static function utc(int $timestamp): string
    {
        return \gmdate('Y-m-d H:i:s', $timestamp);
    }
}
