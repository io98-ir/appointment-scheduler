<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Application;

use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * The admin's view of the online gateways: which are set up, changing the
 * merchant ids and turning WooCommerce on. Each call checks the capability
 * again after the REST permission callback (architecture §12). A merchant id
 * is never returned, only whether it is set.
 */
final class PaymentSettingsService
{
    public const CAPABILITY = 'manage_payments';

    /** Gateway id => the secret that holds its merchant id. */
    public const MERCHANTS = ['zarinpal' => 'zarinpal_merchant', 'zibal' => 'zibal_merchant'];

    public function __construct(private readonly Authorizer $authorizer, private readonly PaymentSettingsStore $store)
    {
    }

    /**
     * @return array{
     *     gateways: list<array{id: string, secret: string, set: bool, fixed: bool}>,
     *     woocommerce: array{available: bool, enabled: bool}
     * }
     */
    public function overview(): array
    {
        $this->authorize();
        $gateways = [];
        foreach (self::MERCHANTS as $id => $secret) {
            $gateways[] = [
                'id' => $id,
                'secret' => $secret,
                'set' => $this->store->hasSecret($secret),
                'fixed' => $this->store->isFixed($secret),
            ];
        }

        return [
            'gateways' => $gateways,
            'woocommerce' => ['available' => $this->store->wooAvailable(), 'enabled' => $this->store->wooEnabled()],
        ];
    }

    /**
     * @param array<string, string> $secrets new merchant ids by secret name; a name that is left
     *     out keeps its value and an empty one removes it.
     * @param bool|null $woocommerce Null keeps the switch.
     * @throws InvalidValue unknown_secret, secret_in_config or invalid_merchant.
     */
    public function update(array $secrets, ?bool $woocommerce): void
    {
        $this->authorize();
        foreach ($secrets as $name => $value) {
            if (!\in_array($name, self::MERCHANTS, true)) {
                throw new InvalidValue('unknown_secret', 'No gateway has this secret.');
            }
            if ($this->store->isFixed($name)) {
                throw new InvalidValue('secret_in_config', 'This value is set in wp-config.php.');
            }
            if ('' !== $value && 1 !== \preg_match('/^[A-Za-z0-9-]{1,64}$/D', $value)) {
                throw new InvalidValue('invalid_merchant', 'A merchant id has letters, digits and dashes only.');
            }
        }
        foreach ($secrets as $name => $value) {
            $this->store->setSecret($name, $value);
        }
        if (null !== $woocommerce) {
            $this->store->setWooEnabled($woocommerce);
        }
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
