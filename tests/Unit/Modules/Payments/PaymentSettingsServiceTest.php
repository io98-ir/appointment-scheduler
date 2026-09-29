<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Payments;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsService;
use Vaqtyar\Modules\Payments\Application\PaymentSettingsStore;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

final class PaymentSettingsServiceTest extends TestCase
{
    public function testTheOverviewNeverHoldsAMerchantId(): void
    {
        $store = new MemoryPaymentStore(['zarinpal_merchant' => 'abcd-1234'], ['zibal_merchant']);

        $overview = $this->service($store, true)->overview();

        self::assertSame(
            [
                ['id' => 'zarinpal', 'secret' => 'zarinpal_merchant', 'set' => true, 'fixed' => false],
                ['id' => 'zibal', 'secret' => 'zibal_merchant', 'set' => true, 'fixed' => true],
            ],
            $overview['gateways']
        );
        self::assertStringNotContainsString('abcd-1234', (string) \json_encode($overview));
    }

    public function testSetsAndRemovesAMerchantAndSwitchesWooCommerce(): void
    {
        $store = new MemoryPaymentStore(['zibal_merchant' => 'old'], []);
        $service = $this->service($store, true);

        $service->update(['zarinpal_merchant' => 'aaaa-bbbb', 'zibal_merchant' => ''], true);

        self::assertSame(['zarinpal_merchant' => 'aaaa-bbbb'], $store->secrets);
        self::assertTrue($store->woo);
    }

    public function testANullSwitchKeepsWooCommerce(): void
    {
        $store = new MemoryPaymentStore([], []);
        $store->woo = true;

        $this->service($store, true)->update([], null);

        self::assertTrue($store->woo);
    }

    public function testRejectsWhatItMustNotStoreAndChangesNothing(): void
    {
        $cases = [
            [['nope' => 'x'], 'unknown_secret'],
            [['zibal_merchant' => 'x'], 'secret_in_config'],
            [['zarinpal_merchant' => 'has space'], 'invalid_merchant'],
            [['zarinpal_merchant' => \str_repeat('a', 65)], 'invalid_merchant'],
        ];
        foreach ($cases as [$secrets, $code]) {
            $store = new MemoryPaymentStore([], ['zibal_merchant']);
            try {
                $this->service($store, true)->update($secrets + ['zarinpal_merchant' => 'fine-1'], true);
                self::fail('Expected InvalidValue.');
            } catch (InvalidValue $e) {
                self::assertSame($code, $e->errorCode);
            }
            self::assertSame([], $store->secrets, 'A rejected update stores nothing, not even the valid part.');
            self::assertFalse($store->woo);
        }
    }

    public function testBothCallsCheckTheCapability(): void
    {
        $service = $this->service(new MemoryPaymentStore([], []), false);

        $calls = [
            static fn () => $service->overview(),
            static fn () => $service->update([], true),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Expected Forbidden.');
            } catch (Forbidden) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function service(PaymentSettingsStore $store, bool $allowed): PaymentSettingsService
    {
        return new PaymentSettingsService(new class ($allowed) implements Authorizer {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->allowed && PaymentSettingsService::CAPABILITY === $capability;
            }
        }, $store);
    }
}
