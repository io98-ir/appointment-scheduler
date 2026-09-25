<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Kernel;

use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Options;
use Vaqtyar\Kernel\RequestId;
use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Shared\SystemClock;

/**
 * With the site's real auth salt and whichever sodium this PHP has (the
 * extension, or WordPress's sodium_compat).
 */
final class SecretStoreTest extends \WP_UnitTestCase
{
    public function testASecretSurvivesTheRoundTripThroughTheDatabase(): void
    {
        $store = $this->store();

        $store->set('zarinpal_merchant', 'xxxx-merchant-id-1234');
        \wp_cache_flush();

        self::assertSame('xxxx-merchant-id-1234', $this->store()->get('zarinpal_merchant'));
        $stored = \get_option(Options::key('secret_zarinpal_merchant'));
        self::assertIsString($stored);
        self::assertStringNotContainsString('merchant', $stored);
    }

    private function store(): SecretStore
    {
        $db = Db::fromGlobals();
        $requestId = new RequestId();

        return new SecretStore(
            SecretStore::keyFromSalt(\wp_salt('auth')),
            new Logger($db, new SystemClock(), $requestId)
        );
    }
}
