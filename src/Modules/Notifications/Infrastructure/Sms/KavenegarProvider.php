<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;

/**
 * Kavenegar (api.kavenegar.com/v1/{key}). Plain text goes to sms/send; a
 * pattern goes to verify/lookup, whose values are token, token2 and token3
 * (so a pattern has at most three) and cannot hold a space: a space in a
 * value becomes a zero-width non-joiner. The answer is
 * {"return": {"status": 200}, "entries": [{"messageid": …}]}.
 */
final class KavenegarProvider implements SmsProvider
{
    private const API = 'https://api.kavenegar.com/v1/';
    private const OK = 200;
    private const TOKENS = ['token', 'token2', 'token3'];
    private const NON_JOINER = "\u{200C}";

    /**
     * @param string $sender empty for Kavenegar's default line.
     */
    public function __construct(
        private readonly SmsHttp $http,
        private readonly string $key,
        private readonly string $sender,
    ) {
    }

    public function id(): string
    {
        return 'kavenegar';
    }

    public function send(string $mobile, string $text): string
    {
        $body = ['receptor' => $mobile, 'message' => $text];
        if ('' !== $this->sender) {
            $body['sender'] = $this->sender;
        }

        return $this->call('sms/send.json', $body);
    }

    /**
     * @param array<string, string> $args
     */
    public function sendPattern(string $mobile, string $code, array $args): string
    {
        if (\count($args) > \count(self::TOKENS)) {
            throw new DeliveryFailed('A Kavenegar pattern takes at most 3 values.');
        }
        $body = ['receptor' => $mobile, 'template' => $code];
        foreach (\array_values($args) as $i => $value) {
            $body[self::TOKENS[$i]] = \str_replace(' ', self::NON_JOINER, Answer::oneLine($value));
        }

        return $this->call('verify/lookup.json', $body);
    }

    /**
     * @param array<string, string> $body
     */
    private function call(string $method, array $body): string
    {
        $answer = $this->http->post(self::API . \rawurlencode($this->key) . '/' . $method, [], $body, false);
        $status = Answer::at($answer, 'return', 'status');
        if (self::OK !== $status) {
            throw new DeliveryFailed('Kavenegar refused the message: status ' . Answer::text($status));
        }

        return Answer::text(Answer::at($answer, 'entries', 0, 'messageid'));
    }
}
