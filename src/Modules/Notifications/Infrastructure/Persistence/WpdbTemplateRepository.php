<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Persistence;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Database\Row;
use Vaqtyar\Kernel\Tables;
use Vaqtyar\Modules\Notifications\Application\TemplateRepository;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\SmsPattern;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * TemplateRepository on the notification_templates table. A row that no
 * longer parses (a hand-edited value) is skipped, so one bad row cannot
 * stop the others from sending.
 */
final class WpdbTemplateRepository implements TemplateRepository
{
    public function __construct(private readonly Db $db, private readonly Clock $clock)
    {
    }

    public function find(int $id): ?Template
    {
        $rows = $this->db->getResults('SELECT * FROM %i WHERE id = %d', Tables::name('notification_templates'), $id);

        return [] === $rows ? null : self::template(new Row($rows[0]));
    }

    /**
     * @return list<Template>
     */
    public function enabledFor(Trigger $trigger): array
    {
        return self::templates($this->db->getResults(
            'SELECT * FROM %i WHERE trigger_type = %s AND enabled = 1 ORDER BY id',
            Tables::name('notification_templates'),
            $trigger->value
        ));
    }

    /**
     * @return list<Template>
     */
    public function all(): array
    {
        return self::templates($this->db->getResults(
            'SELECT * FROM %i ORDER BY id',
            Tables::name('notification_templates')
        ));
    }

    public function save(Template $template): Template
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $values = [
            'trigger_type' => $template->trigger->value,
            'audience' => $template->audience->value,
            'channel' => $template->channel,
            'offset_min' => $template->offsetMin,
            'subject' => $template->subject,
            'body' => $template->body,
            'enabled' => $template->enabled ? 1 : 0,
            'sms_patterns' => self::encode($template->smsPatterns),
            'updated_at' => $now,
        ];
        if (null !== $template->id) {
            $this->db->update(Tables::name('notification_templates'), $values, ['id' => $template->id]);

            return $template;
        }

        return $template->withId($this->db->insert(
            Tables::name('notification_templates'),
            $values + ['created_at' => $now]
        ));
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM %i WHERE id = %d', Tables::name('notification_templates'), $id);
    }

    /**
     * @param list<array<string, string|null>> $rows
     * @return list<Template>
     */
    private static function templates(array $rows): array
    {
        $templates = [];
        foreach ($rows as $values) {
            $template = self::template(new Row($values));
            if (null !== $template) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    private static function template(Row $row): ?Template
    {
        $trigger = Trigger::tryFrom($row->string('trigger_type'));
        $audience = Audience::tryFrom($row->string('audience'));
        if (null === $trigger || null === $audience) {
            return null;
        }
        try {
            return new Template(
                $row->int('id'),
                $trigger,
                $audience,
                $row->string('channel'),
                $row->intOrNull('offset_min'),
                $row->string('subject'),
                $row->string('body'),
                1 === $row->int('enabled'),
                self::decode($row->stringOrNull('sms_patterns'))
            );
        } catch (InvalidValue) {
            return null;
        }
    }

    /**
     * @param array<string, SmsPattern> $patterns
     */
    private static function encode(array $patterns): ?string
    {
        if ([] === $patterns) {
            return null;
        }
        $json = [];
        foreach ($patterns as $provider => $pattern) {
            $json[$provider] = ['code' => $pattern->code, 'args' => $pattern->args];
        }

        return (string) \wp_json_encode($json);
    }

    /**
     * A pattern that no longer parses is dropped: that provider then sends the plain text.
     *
     * @return array<string, SmsPattern>
     */
    private static function decode(?string $stored): array
    {
        $decoded = null === $stored ? null : \json_decode($stored, true);
        $patterns = [];
        foreach (\is_array($decoded) ? $decoded : [] as $provider => $pattern) {
            $code = \is_array($pattern) ? ($pattern['code'] ?? null) : null;
            $args = \is_array($pattern) ? ($pattern['args'] ?? null) : null;
            if (!\is_string($provider) || !\is_string($code) || !\is_array($args)) {
                continue;
            }
            try {
                $patterns[$provider] = new SmsPattern($code, \array_values(\array_filter($args, \is_string(...))));
            } catch (InvalidValue) {
                continue;
            }
        }

        return $patterns;
    }
}
