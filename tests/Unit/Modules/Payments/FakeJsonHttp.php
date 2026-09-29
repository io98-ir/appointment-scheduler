<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use Vaqtyar\Modules\Payments\Application\JsonHttp;

/**
 * JsonHttp for gateway tests: the answer comes from a closure that sees the
 * url and body, and every call is kept to assert on.
 */
final class FakeJsonHttp implements JsonHttp
{
    /** @var list<array{url: string, body: array<string, mixed>}> */
    public array $calls = [];

    /**
     * @param \Closure(string, array<string, mixed>): array<mixed> $answer
     */
    public function __construct(private readonly \Closure $answer)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    public function post(string $url, array $body): array
    {
        $this->calls[] = ['url' => $url, 'body' => $body];

        return ($this->answer)($url, $body);
    }
}
