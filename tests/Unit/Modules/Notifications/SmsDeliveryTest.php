<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\Message;
use Vaqtyar\Modules\Notifications\Application\OtpSms;
use Vaqtyar\Modules\Notifications\Application\SmsAdminService;
use Vaqtyar\Modules\Notifications\Application\SmsChannel;
use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;
use Vaqtyar\Modules\Notifications\Application\SmsSender;
use Vaqtyar\Modules\Notifications\Domain\SmsNumber;
use Vaqtyar\Modules\Notifications\Domain\SmsPattern;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SmsProviders;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SmsSettings;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

final class SmsDeliveryTest extends TestCase
{
    private const PHONE = '+989121234567';

    /** @var \ArrayObject<int, string> what the providers were asked, in order. */
    private \ArrayObject $calls;

    protected function setUp(): void
    {
        $this->calls = new \ArrayObject();
    }

    public function testTheFirstProviderThatAcceptsWinsAndFailoverSkipsOneThatFails(): void
    {
        $sender = new SmsSender([$this->provider('a', fail: true), $this->provider('b'), $this->provider('c')]);

        self::assertSame('b:ref-b', $sender->send(self::PHONE, 'hi'));
        self::assertSame(['a text 09121234567', 'b text 09121234567'], $this->calls->getArrayCopy());
    }

    public function testAProviderWithAPatternSendsByItAndTheOthersSendText(): void
    {
        $sender = new SmsSender([$this->provider('a'), $this->provider('b', fail: true), $this->provider('c')]);
        $patterns = ['b' => new SmsPattern('p9', ['code', 'name']), 'a' => new SmsPattern('p1', ['code'])];

        $sender->send(self::PHONE, 'hi', $patterns, ['code' => '12', 'name' => 'Ali']);
        self::assertSame(['a pattern p1 code=12'], $this->calls->getArrayCopy());

        $this->calls = new \ArrayObject();
        (new SmsSender([$this->provider('b', fail: true), $this->provider('c')]))
            ->send(self::PHONE, 'hi', $patterns, ['code' => '12', 'name' => 'Ali']);
        self::assertSame(['b pattern p9 code=12,name=Ali', 'c text 09121234567'], $this->calls->getArrayCopy());
    }

    public function testEveryProviderFailingNamesEachReason(): void
    {
        $sender = new SmsSender([$this->provider('a', fail: true), $this->provider('b', fail: true)]);

        try {
            $sender->send(self::PHONE, 'hi');
            self::fail('Expected DeliveryFailed.');
        } catch (DeliveryFailed $e) {
            self::assertSame('a: a is down; b: b is down', $e->getMessage());
        }
    }

    public function testWithoutProvidersOrAnIranianMobileNothingIsSent(): void
    {
        self::assertFalse((new SmsSender([]))->isConfigured());
        $one = [$this->provider('a')];
        foreach ([[[], self::PHONE], [$one, '+441234567890'], [$one, '+982112345678']] as [$providers, $phone]) {
            try {
                (new SmsSender($providers))->send($phone, 'hi');
                self::fail('Expected DeliveryFailed.');
            } catch (DeliveryFailed) {
                self::assertSame([], $this->calls->getArrayCopy());
            }
        }
    }

    public function testTheChannelHandsTheTemplateToTheSender(): void
    {
        $channel = new SmsChannel(new SmsSender([$this->provider('a')]));
        $patterns = ['a' => new SmsPattern('p1', ['name'])];
        $message = new Message(self::PHONE, '', 'Hello Ali', ['name' => 'Ali'], $patterns);

        self::assertSame('sms', $channel->id());
        self::assertSame('phone', $channel->address());
        self::assertSame('a:ref-a', $channel->send($message));
        self::assertSame(['a pattern p1 name=Ali'], $this->calls->getArrayCopy());
    }

    public function testTheLoginCodeGoesByPatternWhereOneIsSetAndIsPlainTextElsewhere(): void
    {
        $config = new FakeConfigStore(new SmsConfig(['kavenegar', 'ippanel'], [], ['kavenegar' => 'otp1']));
        $sender = new SmsSender([$this->provider('kavenegar', fail: true), $this->provider('ippanel')]);
        $otp = new OtpSms(static fn (): SmsSender => $sender, $config);

        $otp->send(self::PHONE, '4821');

        self::assertSame(
            ['kavenegar pattern otp1 code=4821', 'ippanel text 09121234567'],
            $this->calls->getArrayCopy()
        );
    }

    public function testTheLoginCodeIsQuietlySkippedWhenNoProviderIsSetUp(): void
    {
        $none = new SmsSender([]);

        (new OtpSms(static fn (): SmsSender => $none, new FakeConfigStore(new SmsConfig())))->send(self::PHONE, '4821');

        self::assertSame([], $this->calls->getArrayCopy());
    }

    public function testOnlyProvidersWithTheirSecretsAndSenderLineAreBuilt(): void
    {
        $secrets = new FakeSecrets(['sms_kavenegar_key' => 'k', 'sms_ippanel_key' => 'i', 'sms_smsir_key' => 's']);
        $config = new SmsConfig(['smsir', 'ippanel', 'kavenegar', 'melipayamak'], ['smsir' => '30007732']);

        $providers = SmsProviders::build($config, $secrets, new FakeSmsHttp());

        // ippanel has no sender line, melipayamak has no credentials.
        self::assertSame(
            ['smsir', 'kavenegar'],
            \array_map(static fn (SmsProvider $p): string => $p->id(), $providers)
        );
    }

    public function testAdminSeesWhichSecretsAreSetButNeverTheirValues(): void
    {
        $secrets = new FakeSecrets(['sms_kavenegar_key' => 'TOP-SECRET']);
        $service = $this->admin($secrets, new FakeConfigStore(new SmsConfig()));

        $overview = $service->overview();

        self::assertStringNotContainsString('TOP-SECRET', (string) \json_encode($overview['providers']));
        self::assertSame(
            ['kavenegar', true, [['name' => 'sms_kavenegar_key', 'set' => true, 'fixed' => false]]],
            [
                $overview['providers'][0]['id'],
                $overview['providers'][0]['configured'],
                $overview['providers'][0]['secrets'],
            ]
        );
        self::assertFalse($overview['providers'][1]['configured']);
    }

    public function testUpdateSavesTheConfigAndSecretsAndAnEmptySecretRemovesIt(): void
    {
        $secrets = new FakeSecrets(['sms_smsir_key' => 'old', 'sms_ippanel_key' => 'keep']);
        $store = new FakeConfigStore(new SmsConfig());
        $config = new SmsConfig(['ippanel'], ['ippanel' => '+983000505']);

        $this->admin($secrets, $store)->update($config, ['sms_smsir_key' => '', 'sms_kavenegar_key' => 'new']);

        self::assertSame($config, $store->config);
        self::assertSame(['sms_ippanel_key' => 'keep', 'sms_kavenegar_key' => 'new'], $secrets->values);
    }

    public function testUpdateRefusesAnUnknownSecretAndOneFixedInWpConfig(): void
    {
        $secrets = new FakeSecrets(['sms_smsir_key' => 'x'], ['sms_smsir_key']);
        $service = $this->admin($secrets, new FakeConfigStore(new SmsConfig()));

        foreach ([['nope' => 'x'], ['sms_smsir_key' => 'y']] as $bad) {
            try {
                $service->update(new SmsConfig(), $bad);
                self::fail('Expected InvalidValue.');
            } catch (InvalidValue $e) {
                self::assertContains($e->errorCode, ['unknown_secret', 'secret_in_config']);
            }
        }
        self::assertSame(['sms_smsir_key' => 'x'], $secrets->values);
    }

    public function testAdminCallsNeedTheCapability(): void
    {
        $service = new SmsAdminService(
            new class implements Authorizer {
                public function allows(string $capability): bool
                {
                    return false;
                }
            },
            new FakeConfigStore(new SmsConfig()),
            new FakeSecrets([]),
            fn (): SmsSender => new SmsSender([])
        );

        $this->expectException(Forbidden::class);
        $service->overview();
    }

    public function testTheTestMessageGoesThroughFailoverAndFailuresAreReported(): void
    {
        $sender = new SmsSender([$this->provider('a')]);
        $service = new SmsAdminService(
            $this->allowAll(),
            new FakeConfigStore(new SmsConfig()),
            new FakeSecrets([]),
            static fn (): SmsSender => $sender
        );

        self::assertSame('a:ref-a', $service->test('0912 123 4567', 'test'));
        self::assertSame(['a text 09121234567'], $this->calls->getArrayCopy());

        foreach (['not a phone' => 'invalid_phone', '+441234567890' => 'sms_failed'] as $phone => $code) {
            try {
                $service->test((string) $phone, 'test');
                self::fail('Expected InvalidValue.');
            } catch (InvalidValue $e) {
                self::assertSame($code, $e->errorCode);
            }
        }
    }

    public function testSettingsRoundTripAndABadStoredValueFallsBackToTheDefaults(): void
    {
        $settings = new SmsSettings(new SmsConfig(['smsir', 'kavenegar'], ['smsir' => '3000'], ['kavenegar' => 'otp']));

        self::assertEquals($settings, SmsSettings::fromStored($settings->toStored()));
        self::assertEquals(new SmsSettings(), SmsSettings::fromStored(['order' => ['kavenegar', 'kavenegar']]));
        self::assertSame(['kavenegar', 'ippanel', 'smsir', 'melipayamak'], SmsSettings::fromStored([])->config->order);
    }

    public function testPatternsAndNumbers(): void
    {
        self::assertSame('09121234567', SmsNumber::local('+989121234567'));
        self::assertNull(SmsNumber::local('+982112345678'));
        self::assertSame('+989121234567', SmsNumber::international('09121234567'));
        self::assertSame(['code' => '1', 'name' => ''], (new SmsPattern('p', ['code', 'name']))->fill(['code' => '1']));
        foreach ([['bad code!', []], ['p', ['a', 'a']], ['p', ['Bad']], ['p', \range('a', 'k')]] as [$code, $args]) {
            try {
                new SmsPattern($code, $args);
                self::fail('Expected InvalidValue.');
            } catch (InvalidValue $e) {
                self::assertSame('invalid_sms_pattern', $e->errorCode);
            }
        }
    }

    private function provider(string $id, bool $fail = false): SmsProvider
    {
        return new class ($id, $fail, $this->calls) implements SmsProvider {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(
                private readonly string $id,
                private readonly bool $fail,
                private readonly \ArrayObject $calls
            ) {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function send(string $mobile, string $text): string
            {
                return $this->record('text ' . $mobile);
            }

            /**
             * @param array<string, string> $args
             */
            public function sendPattern(string $mobile, string $code, array $args): string
            {
                $values = [];
                foreach ($args as $name => $value) {
                    $values[] = $name . '=' . $value;
                }

                return $this->record('pattern ' . $code . ' ' . \implode(',', $values));
            }

            private function record(string $call): string
            {
                $this->calls->append($this->id . ' ' . $call);
                if ($this->fail) {
                    throw new DeliveryFailed($this->id . ' is down');
                }

                return 'ref-' . $this->id;
            }
        };
    }

    private function admin(FakeSecrets $secrets, FakeConfigStore $store): SmsAdminService
    {
        return new SmsAdminService($this->allowAll(), $store, $secrets, fn (): SmsSender => new SmsSender([]));
    }

    private function allowAll(): Authorizer
    {
        return new class implements Authorizer {
            public function allows(string $capability): bool
            {
                return true;
            }
        };
    }
}
