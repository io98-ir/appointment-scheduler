<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Pagination;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Notifications\Application\NotificationAdminService;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\SmsPattern;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The admin notifications API (docs/api.md):
 *
 *     GET    /notification-templates        every template
 *     POST   /notification-templates        create: 201 with the template
 *     PUT    /notification-templates/{id}   replace every field: 200 with the template
 *     DELETE /notification-templates/{id}   204
 *     GET    /notification-log              what was sent, newest first, paged
 *
 * Each needs the notifications capability, which NotificationAdminService
 * checks again. offset_min is required for a reminder and null for the rest.
 */
final class NotificationRoutes
{
    private const ID = ['id' => ['type' => 'integer', 'minimum' => 1, 'required' => true]];

    /**
     * @param \Closure(): NotificationAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(NotificationAdminService::CAPABILITY));

        $this->router->add(
            '/notification-templates',
            'GET',
            fn (): array => \array_map(self::toJson(...), ($this->service)()->templates()),
            $allowed
        );
        $this->router->add(
            '/notification-templates',
            'POST',
            fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(
                self::toJson(($this->service)()->save(self::fromRequest($request, null))),
                201
            ),
            $allowed,
            self::fields()
        );
        $this->router->add(
            '/notification-templates/(?P<id>\d+)',
            'PUT',
            fn (\WP_REST_Request $request): array => self::toJson(
                ($this->service)()->save(self::fromRequest($request, self::id($request)))
            ),
            $allowed,
            self::ID + self::fields()
        );
        $this->router->add(
            '/notification-templates/(?P<id>\d+)',
            'DELETE',
            function (\WP_REST_Request $request): \WP_REST_Response {
                ($this->service)()->delete(self::id($request));

                return new \WP_REST_Response(null, 204);
            },
            $allowed,
            self::ID
        );
        $this->router->add(
            '/notification-log',
            'GET',
            function (\WP_REST_Request $request): \WP_REST_Response {
                $pagination = Pagination::fromRequest($request);
                $page = ($this->service)()->log($pagination->offset(), $pagination->perPage);

                return $pagination->response(\array_map(self::entryJson(...), $page->items), $page->total);
            },
            $allowed,
            Pagination::ARGS
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fields(): array
    {
        return [
            'trigger' => [
                'type' => 'string',
                'enum' => \array_map(static fn (Trigger $t): string => $t->value, Trigger::cases()),
                'required' => true,
            ],
            'audience' => [
                'type' => 'string',
                'enum' => \array_map(static fn (Audience $a): string => $a->value, Audience::cases()),
                'required' => true,
            ],
            'channel' => ['type' => 'string', 'maxLength' => 32, 'required' => true],
            'offset_min' => ['type' => ['integer', 'null'], 'minimum' => 1, 'default' => null],
            'subject' => ['type' => 'string', 'maxLength' => Template::MAX_SUBJECT, 'default' => ''],
            'body' => ['type' => 'string', 'maxLength' => Template::MAX_BODY, 'required' => true],
            'enabled' => ['type' => 'boolean', 'default' => true],
            'sms_patterns' => ['type' => 'object', 'default' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toJson(Template $template): array
    {
        return [
            'id' => $template->id,
            'trigger' => $template->trigger->value,
            'audience' => $template->audience->value,
            'channel' => $template->channel,
            'offset_min' => $template->offsetMin,
            'subject' => $template->subject,
            'body' => $template->body,
            'enabled' => $template->enabled,
            'sms_patterns' => (object) \array_map(
                static fn (SmsPattern $pattern): array => ['code' => $pattern->code, 'args' => $pattern->args],
                $template->smsPatterns
            ),
        ];
    }

    /**
     * @param array{
     *     id: int, template_id: int, channel: string, recipient: string, status: string,
     *     provider_ref: ?string, error: ?string, sent_at: ?int, created_at: int
     * } $entry
     * @return array<string, mixed>
     */
    private static function entryJson(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'template_id' => $entry['template_id'],
            'channel' => $entry['channel'],
            'recipient' => $entry['recipient'],
            'status' => $entry['status'],
            'provider_ref' => $entry['provider_ref'],
            'error' => $entry['error'],
            'sent_at' => null === $entry['sent_at'] ? null : \gmdate('Y-m-d\TH:i:s\Z', $entry['sent_at']),
            'created_at' => \gmdate('Y-m-d\TH:i:s\Z', $entry['created_at']),
        ];
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function fromRequest(\WP_REST_Request $request, ?int $id): Template
    {
        $p = $request->get_params();
        $offset = $p['offset_min'] ?? null;

        return new Template(
            $id,
            Trigger::from(self::string($p['trigger'] ?? null)),
            Audience::from(self::string($p['audience'] ?? null)),
            self::string($p['channel'] ?? null),
            null === $offset ? null : self::intValue($offset),
            \trim(self::string($p['subject'] ?? '')),
            self::string($p['body'] ?? null),
            (bool) ($p['enabled'] ?? true),
            self::patterns($p['sms_patterns'] ?? [])
        );
    }

    /**
     * @return array<string, SmsPattern>
     * @throws InvalidValue invalid_sms_pattern for anything but {provider: {code, args: [name…]}}.
     */
    private static function patterns(mixed $value): array
    {
        $patterns = [];
        foreach (\is_array($value) ? $value : [] as $provider => $pattern) {
            $code = \is_array($pattern) ? ($pattern['code'] ?? null) : null;
            $args = \is_array($pattern) ? ($pattern['args'] ?? []) : null;
            if (!\is_string($provider) || !\is_string($code) || !\is_array($args) || !\array_is_list($args)) {
                throw new InvalidValue('invalid_sms_pattern', 'A pattern is {"code": "…", "args": ["name", …]}.');
            }
            $patterns[$provider] = new SmsPattern($code, \array_values(\array_filter($args, \is_string(...))));
        }

        return $patterns;
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    private static function id(\WP_REST_Request $request): int
    {
        return self::intValue($request->get_url_params()['id'] ?? null);
    }

    private static function intValue(mixed $value): int
    {
        return \is_int($value) || \is_numeric($value) ? (int) $value : throw self::wrongType();
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw self::wrongType();
    }

    private static function wrongType(): \LogicException
    {
        return new \LogicException('The schema let a wrong type through.');
    }
}
