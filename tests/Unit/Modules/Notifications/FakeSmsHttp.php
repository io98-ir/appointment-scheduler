<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use Vaqtyar\Modules\Notifications\Application\SmsHttp;

/**
 * SmsHttp for provider tests: the answer is fixed and every call is kept to
 * assert on.
 */
final class FakeSmsHttp implements SmsHttp
{
    /** @var list<array{url: string, headers: array<string, string>, body: array<string, mixed>, json: bool}> */
    public array $calls = [];

    /**
     * @param array<mixed> $answer
     */
    public function __construct(public array $answer = [])
    {
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function post(string $url, array $headers, array $body, bool $json = true): array
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body, 'json' => $json];

        return $this->answer;
    }
}
