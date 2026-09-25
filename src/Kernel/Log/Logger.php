<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Log;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Shared\Domain\Clock;

/**
 * The plugin's log: the logs table, with the request id on every line and
 * personal data masked (architecture §12, §13).
 *
 * Logging never throws. When the table cannot be written (it is missing
 * because a migration failed, or the database is down), the line goes to the
 * PHP error log instead, masked the same way.
 *
 * A line written inside a Transaction is rolled back with it: log a failure
 * after the transaction, where it is caught. Old lines are pruned only
 * outside a transaction.
 *
 * Masking (Pii) catches emails and numbers, not names or addresses: keep
 * those out of messages and context.
 */
final class Logger
{
    public const RETENTION_DAYS = 30;

    private const TABLE = 'logs';

    /** Characters; the rest is cut. */
    private const MAX_MESSAGE = 1000;

    /** Bytes of JSON; a larger context is replaced by a marker. */
    private const MAX_CONTEXT = 16384;

    /** Nesting kept in the context; deeper values become a marker. */
    private const MAX_DEPTH = 5;

    /** Expired rows removed per write; logging is rare, so this keeps up. */
    private const PRUNE_BATCH = 100;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly RequestId $requestId,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $channel, string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $channel, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $channel, string $message, array $context = []): void
    {
        $this->log(LogLevel::Warning, $channel, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $channel, string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $channel, $message, $context);
    }

    /**
     * @param string $channel Where it happened, e.g. "rest", "payments".
     * @param string $message Constant text is best; data goes in the context.
     * @param array<string, mixed> $context Strings are masked; a Throwable
     *     keeps its class, code, masked message, file and line.
     */
    public function log(LogLevel $level, string $channel, string $message, array $context = []): void
    {
        // Masked before it is cut, so a cut cannot leave a partial number unmasked.
        $message = \mb_substr(Pii::mask(\mb_scrub($message, 'UTF-8')), 0, self::MAX_MESSAGE);
        $channel = \substr($channel, 0, 32);
        $context = [] === $context ? null : self::encode($context);
        $now = $this->clock->now()->getTimestamp();

        try {
            $this->db->insert(Tables::name(self::TABLE), [
                'level' => $level->value,
                'channel' => $channel,
                'message' => $message,
                'context' => $context,
                'request_id' => $this->requestId->value(),
                'created_at' => \gmdate('Y-m-d H:i:s', $now),
            ]);
        } catch (\Throwable) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the fallback when the table cannot be written.
            \error_log(\sprintf(
                '%s [%s] %s: %s (request %s)%s',
                Identity::NAME,
                $level->value,
                $channel,
                $message,
                $this->requestId->value(),
                null === $context ? '' : ' ' . $context
            ));

            return;
        }

        if ($this->db->inTransaction()) {
            // Deleting takes row locks that would last to the caller's commit,
            // next to its booking locks. The next line outside one prunes.
            return;
        }

        try {
            $this->db->execute(
                'DELETE FROM %i WHERE created_at < %s LIMIT ' . self::PRUNE_BATCH,
                Tables::name(self::TABLE),
                \gmdate('Y-m-d H:i:s', $now - self::RETENTION_DAYS * 86400)
            );
        } catch (\Throwable) {
            // The line is written; old rows wait for the next one.
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function encode(array $context): string
    {
        $json = \wp_json_encode(
            self::normalize($context, 0),
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE
            | \JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (false === $json || \strlen($json) > self::MAX_CONTEXT) {
            return '{"truncated":true}';
        }

        return $json;
    }

    private static function normalize(mixed $value, int $depth): mixed
    {
        return match (true) {
            \is_string($value) => Pii::mask($value),
            null === $value, \is_bool($value), \is_int($value), \is_float($value) => $value,
            $value instanceof \Throwable => [
                'class' => $value::class,
                'code' => $value->getCode(),
                'message' => Pii::mask($value->getMessage()),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ],
            $value instanceof \BackedEnum => $value->value,
            \is_array($value) => $depth >= self::MAX_DEPTH ? '[too deep]' : self::normalizeArray($value, $depth),
            \is_object($value) => $value::class,
            default => \get_debug_type($value),
        };
    }

    /**
     * Keys are masked too: a list keyed by recipient is a natural shape.
     *
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function normalizeArray(array $value, int $depth): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[\is_string($key) ? Pii::mask($key) : $key] = self::normalize($item, $depth + 1);
        }

        return $normalized;
    }
}
