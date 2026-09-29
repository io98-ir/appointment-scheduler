<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\IpPanelProvider;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\KavenegarProvider;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\MelipayamakProvider;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SmsIrProvider;

/**
 * The contract every SMS provider keeps, against the answers each documents:
 * plain text and pattern both return the provider's reference, a refusal is
 * a DeliveryFailed, and the request has the number, the credentials and the
 * text or pattern in the shape that provider asks for.
 */
final class SmsProvidersTest extends TestCase
{
    private const MOBILE = '09121234567';

    /**
     * @return iterable<string, array{
     *     string, \Closure(FakeSmsHttp): SmsProvider, array<mixed>, array<mixed>, string
     * }> id, factory, success answer, refusal, reference.
     */
    public static function providers(): iterable
    {
        yield 'kavenegar' => [
            'kavenegar',
            static fn (FakeSmsHttp $http): SmsProvider => new KavenegarProvider($http, 'KEY=', '10004346'),
            ['return' => ['status' => 200, 'message' => 'ok'], 'entries' => [['messageid' => 8792343]]],
            ['return' => ['status' => 411, 'message' => 'bad receptor']],
            '8792343',
        ];
        yield 'ippanel' => [
            'ippanel',
            static fn (FakeSmsHttp $http): SmsProvider => new IpPanelProvider($http, 'KEY', '+983000505'),
            ['status' => 'OK', 'code' => 200, 'data' => ['message_id' => 4455]],
            ['status' => 'Bad Request', 'code' => 400],
            '4455',
        ];
        yield 'smsir' => [
            'smsir',
            static fn (FakeSmsHttp $http): SmsProvider => new SmsIrProvider($http, 'KEY', '30007732'),
            ['status' => 1, 'message' => 'ok', 'data' => ['packId' => 'pack-1', 'messageIds' => [9], 'messageId' => 9]],
            ['status' => 101, 'message' => 'bad key', 'data' => null],
            'pack-1',
        ];
        yield 'melipayamak' => [
            'melipayamak',
            static fn (FakeSmsHttp $http): SmsProvider => new MelipayamakProvider($http, 'user', 'pass', '5000'),
            ['Value' => '1234567890123456', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'],
            ['Value' => '2', 'RetStatus' => 2, 'StrRetStatus' => 'bad credentials'],
            '1234567890123456',
        ];
    }

    /**
     * @param \Closure(FakeSmsHttp): SmsProvider $make
     * @param array<mixed> $ok
     * @param array<mixed> $refusal
     *
     * @dataProvider providers
     */
    public function testItReturnsTheProvidersReference(
        string $id,
        \Closure $make,
        array $ok,
        array $refusal,
        string $reference
    ): void {
        $provider = $make(new FakeSmsHttp($ok));

        self::assertSame($id, $provider->id());
        self::assertSame($reference, $provider->send(self::MOBILE, 'hello'));
        self::assertNotSame('', $provider->sendPattern(self::MOBILE, '123', ['code' => '4821']));
    }

    /**
     * @param \Closure(FakeSmsHttp): SmsProvider $make
     * @param array<mixed> $ok
     * @param array<mixed> $refusal
     *
     * @dataProvider providers
     */
    public function testARefusalIsADeliveryFailure(
        string $id,
        \Closure $make,
        array $ok,
        array $refusal,
        string $reference
    ): void {
        $provider = $make(new FakeSmsHttp($refusal));

        $this->expectException(DeliveryFailed::class);
        $provider->send(self::MOBILE, 'hello');
    }

    /**
     * @param \Closure(FakeSmsHttp): SmsProvider $make
     * @param array<mixed> $ok
     * @param array<mixed> $refusal
     *
     * @dataProvider providers
     */
    public function testARefusedPatternIsADeliveryFailureToo(
        string $id,
        \Closure $make,
        array $ok,
        array $refusal,
        string $reference
    ): void {
        $provider = $make(new FakeSmsHttp($refusal));

        $this->expectException(DeliveryFailed::class);
        $provider->sendPattern(self::MOBILE, '123', ['code' => '4821']);
    }

    /**
     * @param \Closure(FakeSmsHttp): SmsProvider $make
     * @param array<mixed> $ok
     * @param array<mixed> $refusal
     *
     * @dataProvider providers
     */
    public function testAnAnswerWithoutTheExpectedShapeIsNeverASuccess(
        string $id,
        \Closure $make,
        array $ok,
        array $refusal,
        string $reference
    ): void {
        $provider = $make(new FakeSmsHttp(['unexpected' => true]));

        $this->expectException(DeliveryFailed::class);
        $provider->send(self::MOBILE, 'hello');
    }

    public function testKavenegarSendsAFormToTheKeyedUrlWithTheSenderLine(): void
    {
        $http = new FakeSmsHttp(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]]);

        (new KavenegarProvider($http, 'A+B=', '1000'))->send(self::MOBILE, 'hello');

        $call = $http->calls[0];
        self::assertSame('https://api.kavenegar.com/v1/A%2BB%3D/sms/send.json', $call['url']);
        self::assertFalse($call['json']);
        self::assertSame(['receptor' => self::MOBILE, 'message' => 'hello', 'sender' => '1000'], $call['body']);
    }

    public function testKavenegarLeavesTheSenderOutWhenThereIsNone(): void
    {
        $http = new FakeSmsHttp(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]]);

        (new KavenegarProvider($http, 'k', ''))->send(self::MOBILE, 'hello');

        self::assertArrayNotHasKey('sender', $http->calls[0]['body']);
    }

    public function testKavenegarPatternValuesAreTokensWithoutSpaces(): void
    {
        $http = new FakeSmsHttp(['return' => ['status' => 200], 'entries' => [['messageid' => 1]]]);

        (new KavenegarProvider($http, 'k', ''))->sendPattern(
            self::MOBILE,
            'booked',
            ['customer_name' => 'Ali Karimi', 'code' => "AB\n12"]
        );

        $call = $http->calls[0];
        self::assertStringEndsWith('/verify/lookup.json', $call['url']);
        self::assertSame(
            [
                'receptor' => self::MOBILE,
                'template' => 'booked',
                'token' => "Ali\u{200C}Karimi",
                'token2' => "AB\u{200C}12",
            ],
            $call['body']
        );
    }

    public function testKavenegarTakesAtMostThreePatternValues(): void
    {
        $provider = new KavenegarProvider(new FakeSmsHttp(), 'k', '');

        $this->expectException(DeliveryFailed::class);
        $provider->sendPattern(self::MOBILE, 'p', ['a' => '1', 'b' => '2', 'c' => '3', 'd' => '4']);
    }

    public function testIpPanelUsesTheAccessKeyAndInternationalNumbers(): void
    {
        $http = new FakeSmsHttp(['status' => 'OK', 'data' => ['message_id' => 1]]);
        $provider = new IpPanelProvider($http, 'KEY', '+983000505');

        $provider->send(self::MOBILE, 'hello');
        $provider->sendPattern(self::MOBILE, 'p1', ['code' => '4821']);

        self::assertSame('AccessKey KEY', $http->calls[0]['headers']['Authorization']);
        self::assertSame(['+989121234567'], $http->calls[0]['body']['recipients']);
        self::assertSame('https://rest.ippanel.com/v1/messages/patterns/send', $http->calls[1]['url']);
        self::assertSame('+989121234567', $http->calls[1]['body']['recipient']);
        self::assertEquals((object) ['code' => '4821'], $http->calls[1]['body']['values']);
    }

    public function testSmsIrSendsANumericLineAndNamedParameters(): void
    {
        $http = new FakeSmsHttp(['status' => 1, 'data' => ['packId' => 'p', 'messageId' => 5]]);
        $provider = new SmsIrProvider($http, 'KEY', '30007732');

        $provider->send(self::MOBILE, 'hello');
        $provider->sendPattern(self::MOBILE, '100001', ['code' => '4821', 'service' => "Cut\nhair"]);

        self::assertSame('KEY', $http->calls[0]['headers']['X-API-KEY']);
        self::assertSame(30007732, $http->calls[0]['body']['lineNumber']);
        self::assertSame([self::MOBILE], $http->calls[0]['body']['mobiles']);
        self::assertSame(100001, $http->calls[1]['body']['templateId']);
        self::assertSame(
            [['name' => 'code', 'value' => '4821'], ['name' => 'service', 'value' => 'Cut hair']],
            $http->calls[1]['body']['parameters']
        );
    }

    public function testMelipayamakJoinsPatternValuesWithSemicolons(): void
    {
        $http = new FakeSmsHttp(['RetStatus' => 1, 'Value' => '99']);
        $provider = new MelipayamakProvider($http, 'user', 'pass', '5000');

        $provider->sendPattern(self::MOBILE, '777', ['name' => 'Ali;Reza', 'code' => 'AB12']);

        $body = $http->calls[0]['body'];
        self::assertSame('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', $http->calls[0]['url']);
        self::assertSame(['user', 'pass', 777, 'Ali Reza;AB12'], [
            $body['username'],
            $body['password'],
            $body['bodyId'],
            $body['text'],
        ]);
    }
}
