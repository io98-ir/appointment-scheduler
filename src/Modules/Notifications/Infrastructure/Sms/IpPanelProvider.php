<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;
use Vaqtyar\Modules\Notifications\Domain\SmsNumber;

/**
 * IPPanel (rest.ippanel.com/v1), with the key in "Authorization: AccessKey".
 * Numbers are +98…; a pattern's variables are sent by name. The answer is
 * {"status": "OK", "data": {"message_id": …}}.
 */
final class IpPanelProvider implements SmsProvider
{
    private const API = 'https://rest.ippanel.com/v1/';

    public function __construct(
        private readonly SmsHttp $http,
        private readonly string $key,
        private readonly string $originator,
    ) {
    }

    public function id(): string
    {
        return 'ippanel';
    }

    public function send(string $mobile, string $text): string
    {
        return $this->call('messages', [
            'originator' => $this->originator,
            'recipients' => [SmsNumber::international($mobile)],
            'message' => $text,
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function sendPattern(string $mobile, string $code, array $args): string
    {
        return $this->call('messages/patterns/send', [
            'pattern_code' => $code,
            'originator' => $this->originator,
            'recipient' => SmsNumber::international($mobile),
            'values' => (object) $args,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function call(string $method, array $body): string
    {
        $answer = $this->http->post(self::API . $method, ['Authorization' => 'AccessKey ' . $this->key], $body);
        if ('OK' !== Answer::at($answer, 'status')) {
            throw new DeliveryFailed('IPPanel refused the message: code ' . Answer::text(Answer::at($answer, 'code')));
        }

        return Answer::text(Answer::at($answer, 'data', 'message_id'));
    }
}
