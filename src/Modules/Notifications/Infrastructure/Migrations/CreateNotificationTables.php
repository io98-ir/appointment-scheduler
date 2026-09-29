<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\DefaultTemplates;
use Vaqtyar\Shared\SystemClock;

/**
 * notification_templates and notification_log (data-model §2), and the
 * templates a new site starts with. The trigger column is trigger_type:
 * TRIGGER is a reserved word in MySQL. sms_patterns comes with the SMS
 * providers (T5.5), as its own migration.
 */
final class CreateNotificationTables implements Migration
{
    public function up(Db $db): void
    {
        $db->createTable(
            Tables::name('notification_templates'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trigger_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            audience VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            channel VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            offset_min INT UNSIGNED NULL,
            subject VARCHAR(191) NOT NULL,
            body TEXT NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY trigger_type (trigger_type, enabled)'
        );
        $db->createTable(
            Tables::name('notification_log'),
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            dedup_key VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            template_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            provider VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
            recipient_masked VARCHAR(191) NOT NULL,
            status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            provider_ref VARCHAR(191) NULL,
            error VARCHAR(191) NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY dedup_key (dedup_key),
            KEY created_at (created_at)'
        );
        // A second run after a failure halfway must not seed twice.
        if ('0' === $db->getVar('SELECT COUNT(*) FROM %i', Tables::name('notification_templates'))) {
            $now = (new SystemClock())->now()->format('Y-m-d H:i:s');
            foreach (DefaultTemplates::all() as $template) {
                $db->insert(Tables::name('notification_templates'), [
                    'trigger_type' => $template->trigger->value,
                    'audience' => $template->audience->value,
                    'channel' => $template->channel,
                    'offset_min' => $template->offsetMin,
                    'subject' => $template->subject,
                    'body' => $template->body,
                    'enabled' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
