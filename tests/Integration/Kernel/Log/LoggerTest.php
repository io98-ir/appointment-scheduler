<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel\Log;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * Writing to the real logs table, which the kernel's own migration created
 * when the plugin booted. Each test's rows are rolled back.
 */
final class LoggerTest extends \WP_UnitTestCase
{
    private FixedClock $clock;

    private RequestId $requestId;

    private Logger $logger;

    private Db $db;

    public function set_up(): void
    {
        parent::set_up();
        $this->clock = new FixedClock('2026-09-25 10:00:30');
        $this->requestId = new RequestId();
        $this->db = Db::fromGlobals();
        $this->logger = new Logger($this->db, $this->clock, $this->requestId);
    }

    public function testALineIsStoredWithValidJsonAndPersianText(): void
    {
        $this->logger->error('sms', 'ارسال پیامک ناموفق بود', ['provider' => 'kavenegar', 'to' => '۰۹۱۲۱۲۳۴۵۶۷']);

        self::assertSame(
            ['error', 'sms', 'ارسال پیامک ناموفق بود', 'kavenegar', '***۴۵۶۷', '2026-09-25 10:00:30'],
            [
                $this->column('level'),
                $this->column('channel'),
                $this->column('message'),
                $this->column("JSON_UNQUOTE(JSON_EXTRACT(context, '$.provider'))"),
                $this->column("JSON_UNQUOTE(JSON_EXTRACT(context, '$.to'))"),
                $this->column('created_at'),
            ]
        );
    }

    public function testLinesPastTheRetentionAreRemovedByTheNextWrite(): void
    {
        $this->logger->info('x', 'old');
        $this->clock->advance(Logger::RETENTION_DAYS * 86400 + 1);

        $this->logger->info('x', 'new');

        self::assertSame(
            'new',
            $this->db->getVar('SELECT GROUP_CONCAT(message) FROM %i WHERE channel = %s', Tables::name('logs'), 'x')
        );
    }

    /**
     * @param literal-string $expression
     */
    private function column(string $expression): ?string
    {
        return $this->db->getVar(
            'SELECT ' . $expression . ' FROM %i WHERE request_id = %s',
            Tables::name('logs'),
            $this->requestId->value()
        );
    }
}
