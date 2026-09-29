<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\RateLimit;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Notifications\Application\NotificationAdminService;
use Vaqtyar\Modules\Notifications\Application\SmsAdminService;
use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Domain\SmsCatalog;

/**
 * The SMS providers API (docs/api.md):
 *
 *     GET  /sms       the configuration, and for each provider whether it is set up
 *     PUT  /sms       replace the configuration; new secret values go in "secrets"
 *     POST /sms/test  send a test message through the failover order
 *
 * Each needs the notifications capability, which SmsAdminService checks
 * again. A secret is never returned, only whether it is set.
 */
final class SmsRoutes
{
    private const TEST_LIMIT = 5;

    /**
     * @param \Closure(): SmsAdminService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(NotificationAdminService::CAPABILITY));

        $this->router->add('/sms', 'GET', fn (): array => self::toJson(($this->service)()->overview()), $allowed);
        $this->router->add(
            '/sms',
            'PUT',
            function (\WP_REST_Request $request): array {
                $p = $request->get_params();
                ($this->service)()->update(
                    new SmsConfig(
                        self::list($p['order'] ?? null),
                        self::map($p['senders'] ?? null, false),
                        self::map($p['otp_patterns'] ?? null, false)
                    ),
                    self::map($p['secrets'] ?? null, true)
                );

                return self::toJson(($this->service)()->overview());
            },
            $allowed,
            [
                'order' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => SmsCatalog::IDS],
                    'default' => [],
                ],
                'senders' => ['type' => 'object', 'default' => []],
                'otp_patterns' => ['type' => 'object', 'default' => []],
                'secrets' => ['type' => 'object', 'default' => []],
            ]
        );
        $this->router->add(
            '/sms/test',
            'POST',
            function (\WP_REST_Request $request): array {
                $p = $request->get_params();
                $text = $p['text'] ?? '';
                $phone = $p['phone'] ?? '';

                return ['reference' => ($this->service)()->test(
                    \is_string($phone) ? $phone : '',
                    \is_string($text) && '' !== \trim($text) ? $text : 'پیامک آزمایشی'
                )];
            },
            $allowed,
            [
                'phone' => ['type' => 'string', 'maxLength' => 32, 'required' => true],
                'text' => ['type' => 'string', 'maxLength' => 500, 'default' => ''],
            ],
            new RateLimit(self::TEST_LIMIT, 60)
        );
    }

    /**
     * @param array{config: SmsConfig, providers: list<array<string, mixed>>} $overview
     * @return array<string, mixed>
     */
    private static function toJson(array $overview): array
    {
        return [
            'order' => $overview['config']->order,
            'senders' => (object) $overview['config']->senders,
            'otp_patterns' => (object) $overview['config']->otpPatterns,
            'providers' => $overview['providers'],
        ];
    }

    /**
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        return \is_array($value) ? \array_values(\array_filter($value, \is_string(...))) : [];
    }

    /**
     * @param bool $keepEmpty an empty secret removes it; an empty sender or code is just not set.
     * @return array<string, string>
     */
    private static function map(mixed $value, bool $keepEmpty): array
    {
        $map = [];
        foreach (\is_array($value) ? $value : [] as $key => $item) {
            if (\is_string($key) && \is_string($item) && ($keepEmpty || '' !== \trim($item))) {
                $map[$key] = \trim($item);
            }
        }

        return $map;
    }
}
