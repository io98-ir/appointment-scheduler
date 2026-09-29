<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;

/**
 * Melipayamak (rest.payamak-panel.com/api/SendSMS), with the account's
 * username and password in the body. A pattern is a "shared service" body
 * id and its values, joined with ";" (so a value cannot hold one). The
 * answer is {"Value": "…", "RetStatus": 1}.
 */
final class MelipayamakProvider implements SmsProvider
{
    private const API = 'https://rest.payamak-panel.com/api/SendSMS/';
    private const OK = 1;

    public function __construct(
        private readonly SmsHttp $http,
        private readonly string $username,
        private readonly string $password,
        private readonly string $from,
    ) {
    }

    public function id(): string
    {
        return 'melipayamak';
    }

    public function send(string $mobile, string $text): string
    {
        return $this->call('SendSMS', ['to' => $mobile, 'from' => $this->from, 'text' => $text, 'isFlash' => false]);
    }

    /**
     * @param array<string, string> $args
     */
    public function sendPattern(string $mobile, string $code, array $args): string
    {
        return $this->call('BaseServiceNumber', [
            'to' => $mobile,
            'bodyId' => (int) $code,
            'text' => \implode(';', \array_map(
                static fn (string $value): string => Answer::oneLine($value, ';'),
                \array_values($args)
            )),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function call(string $method, array $body): string
    {
        $answer = $this->http->post(
            self::API . $method,
            [],
            ['username' => $this->username, 'password' => $this->password] + $body
        );
        if (self::OK !== Answer::at($answer, 'RetStatus')) {
            throw new DeliveryFailed(
                'Melipayamak refused the message: status ' . Answer::text(Answer::at($answer, 'RetStatus'))
            );
        }

        return Answer::text(Answer::at($answer, 'Value'));
    }
}
