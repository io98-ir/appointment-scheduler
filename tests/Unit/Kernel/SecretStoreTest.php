<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Kernel;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Identity;
use Vaqtyar\Kernel\KernelException;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Tests\Fixtures\FixedClock;
use Vaqtyar\Tests\Unit\Kernel\Database\FakesWpdb;

final class SecretStoreTest extends TestCase
{
    use FakesWpdb;

    /** @var array<string, array{mixed, bool|null}> Option => value and autoload. */
    private array $options = [];

    /** @var list<array<string, mixed>> */
    private array $logRows = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->alias(
            fn (string $name, mixed $default = false): mixed => isset($this->options[$name])
                ? $this->options[$name][0]
                : $default
        );
        Functions\when('update_option')->alias(function (string $name, mixed $value, ?bool $autoload = null): bool {
            $this->options[$name] = [$value, $autoload];

            return true;
        });
        Functions\when('delete_option')->alias(function (string $name): bool {
            unset($this->options[$name]);

            return true;
        });
        Functions\when('wp_json_encode')->alias(
            static fn (mixed $value, int $flags = 0): string|false => \json_encode($value, $flags)
        );
        $this->fakeWpdb()->shouldReceive('insert')->andReturnUsing(function (string $table, array $data): int {
            /** @var array<string, mixed> $data */
            $this->logRows[] = $data;

            return 1;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->tearDownWpdb();
        parent::tearDown();
    }

    public function testStoresASecretEncryptedAndReadsItBack(): void
    {
        $store = $this->store();

        $store->set('zarinpal_merchant', 'xxxx-merchant-id-1234');

        [$stored, $autoload] = $this->options[Options::key('secret_zarinpal_merchant')];
        self::assertIsString($stored);
        self::assertStringStartsWith('v1:', $stored);
        self::assertStringNotContainsString('merchant', $stored);
        // Read on few requests: not autoloaded.
        self::assertFalse($autoload);
        self::assertSame('xxxx-merchant-id-1234', $store->get('zarinpal_merchant'));
    }

    public function testTheSameValueEncryptsDifferentlyEachTime(): void
    {
        $store = $this->store();
        $store->set('a', 'same value');
        $store->set('b', 'same value');

        $a = $this->options[Options::key('secret_a')][0];
        $b = $this->options[Options::key('secret_b')][0];
        self::assertIsString($a);
        self::assertIsString($b);
        self::assertNotSame($a, $b);
    }

    public function testAMissingSecretIsNull(): void
    {
        self::assertNull($this->store()->get('kavenegar_api_key'));
    }

    public function testAnEmptyValueDeletesTheSecret(): void
    {
        $store = $this->store();
        $store->set('kavenegar_api_key', 'abc');

        $store->set('kavenegar_api_key', '');

        self::assertArrayNotHasKey(Options::key('secret_kavenegar_api_key'), $this->options);
        self::assertNull($store->get('kavenegar_api_key'));
    }

    public function testASecretDoesNotOpenUnderAnotherName(): void
    {
        $store = $this->store();
        $store->set('zibal_merchant', 'secret');
        // Someone copies the stored value to another secret's option.
        $copied = $this->options[Options::key('secret_zibal_merchant')];
        $this->options[Options::key('secret_zarinpal_merchant')] = $copied;

        self::assertNull($store->get('zarinpal_merchant'));
    }

    public function testAfterTheSaltChangesTheSecretIsUnreadableAndThatIsLogged(): void
    {
        $this->store('old salt')->set('zibal_merchant', 'secret');

        self::assertNull($this->store('new salt')->get('zibal_merchant'));

        self::assertCount(1, $this->logRows);
        self::assertSame('error', $this->logRows[0]['level']);
        self::assertSame('{"secret":"zibal_merchant"}', $this->logRows[0]['context']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function damagedValues(): iterable
    {
        yield 'no version' => ['plain text'];
        yield 'not base64' => ['v1:###'];
        yield 'too short' => ['v1:c2hvcnQ='];
    }

    /**
     * @dataProvider damagedValues
     */
    public function testADamagedValueIsUnreadable(string $stored): void
    {
        $this->options[Options::key('secret_zibal_merchant')] = [$stored, false];

        self::assertNull($this->store()->get('zibal_merchant'));
    }

    public function testAConstantInWpConfigWins(): void
    {
        $constant = \strtoupper(Identity::SLUG) . '_CONFIG_ONLY_KEY';
        if (!\defined($constant)) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- it is prefixed.
            \define($constant, 'from-wp-config');
        }
        $store = $this->store();

        self::assertTrue($store->isDefinedInConfig('config_only_key'));
        self::assertFalse($store->isDefinedInConfig('zibal_merchant'));
        self::assertSame('from-wp-config', $store->get('config_only_key'));

        $this->expectException(KernelException::class);
        $store->set('config_only_key', 'ignored');
    }

    public function testANumericConstantIsReadAsText(): void
    {
        $constant = \strtoupper(Identity::SLUG) . '_NUMERIC_TERMINAL';
        if (!\defined($constant)) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- it is prefixed.
            \define($constant, 1234567);
        }

        self::assertSame('1234567', $this->store()->get('numeric_terminal'));
    }

    public function testAnUnusableConstantIsNullAndLogged(): void
    {
        $constant = \strtoupper(Identity::SLUG) . '_EMPTY_KEY';
        if (!\defined($constant)) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- it is prefixed.
            \define($constant, '');
        }

        self::assertNull($this->store()->get('empty_key'));
        self::assertSame('{"secret":"empty_key"}', $this->logRows[0]['context'] ?? null);
    }

    public function testRejectsAnInvalidName(): void
    {
        $this->expectException(KernelException::class);

        $this->store()->get('Zarinpal-Key');
    }

    public function testRejectsAKeyOfTheWrongLength(): void
    {
        $this->expectException(KernelException::class);

        new SecretStore('short', $this->logger());
    }

    public function testTheKeyDependsOnTheSalt(): void
    {
        self::assertSame(32, \strlen(SecretStore::keyFromSalt('a')));
        self::assertSame(SecretStore::keyFromSalt('a'), SecretStore::keyFromSalt('a'));
        self::assertNotSame(SecretStore::keyFromSalt('a'), SecretStore::keyFromSalt('b'));
    }

    private function store(string $salt = 'salt'): SecretStore
    {
        return new SecretStore(SecretStore::keyFromSalt($salt), $this->logger());
    }

    private function logger(): Logger
    {
        return new Logger(Db::fromGlobals(), new FixedClock('2026-09-25 10:00:00'), new RequestId());
    }
}
