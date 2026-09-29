<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Migrations;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Migration;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\DefaultTemplates;
use Vaqtyar\Shared\SystemClock;

/**
 * notification_templates.sms_patterns (T5.5): a JSON object of pattern code
 * and value names by SMS provider id, and the SMS templates a site starts
 * with. Those send nothing until a provider is set up (the sms channel is
 * not registered before that), so they are enabled from the start.
 */
final class AddSmsPatterns implements Migration
{
    public function up(Db $db): void
    {
        $table = Tables::name('notification_templates');
        $column = $db->getVar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $table,
            'sms_patterns'
        );
        if ('0' === $column) {
            $db->execute('ALTER TABLE %i ADD COLUMN sms_patterns TEXT NULL AFTER body', $table);
        }
        // A second run after a failure halfway must not seed twice.
        if ('0' === $db->getVar('SELECT COUNT(*) FROM %i WHERE channel = %s', $table, 'sms')) {
            $now = (new SystemClock())->now()->format('Y-m-d H:i:s');
            foreach (DefaultTemplates::sms() as $template) {
                $db->insert($table, [
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
