<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;

/**
 * SMS.ir (api.sms.ir/v1), with the key in "X-API-KEY". Plain text goes to
 * send/bulk from a line number; a pattern is a "verify" template whose
 * parameters are sent by name. The answer is {"status": 1, "data": {…}}.
 */
final class SmsIrProvider implements SmsProvider
{
    private const API = 'https://api.sms.ir/v1/send/';
    private const OK = 1;

    public function __construct(
        private readonly SmsHttp $http,
        private readonly string $key,
        private readonly string $line,
    ) {
    }

    public function id(): string
    {
        return 'smsir';
    }

    public function send(string $mobile, string $text): string
    {
        $answer = $this->call('bulk', [
            'lineNumber' => (int) $this->line,
            'messageText' => $text,
            'mobiles' => [$mobile],
        ]);
        $reference = Answer::at($answer, 'data', 'packId');

        return Answer::text($reference ?? Answer::at($answer, 'data', 'messageIds', 0));
    }

    /**
     * @param array<string, string> $args
     */
    public function sendPattern(string $mobile, string $code, array $args): string
    {
        $parameters = [];
        foreach ($args as $name => $value) {
            $parameters[] = ['name' => $name, 'value' => Answer::oneLine($value)];
        }
        $answer = $this->call('verify', [
            'mobile' => $mobile,
            'templateId' => (int) $code,
            'parameters' => $parameters,
        ]);

        return Answer::text(Answer::at($answer, 'data', 'messageId'));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    private function call(string $method, array $body): array
    {
        $answer = $this->http->post(self::API . $method, ['X-API-KEY' => $this->key], $body);
        if (self::OK !== Answer::at($answer, 'status')) {
            throw new DeliveryFailed(
                'SMS.ir refused the message: status ' . Answer::text(Answer::at($answer, 'status'))
            );
        }

        return $answer;
    }
}
