<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\SmsCatalog;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

/**
 * The admin's view of the SMS providers: what is set up, changing it, and a
 * test message. Each call checks the capability again after the REST
 * permission callback (architecture §12).
 */
final class SmsAdminService
{
    /**
     * @param \Closure(): SmsSender $sender Built when called, so it sees what update() saved.
     */
    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly SmsConfigStore $config,
        private readonly SmsSecrets $secrets,
        private readonly \Closure $sender,
    ) {
    }

    /**
     * @return array{
     *     config: SmsConfig,
     *     providers: list<array{
     *         id: string, configured: bool, secrets: list<array{name: string, set: bool, fixed: bool}>
     *     }>
     * } No secret's value, only whether it is set.
     */
    public function overview(): array
    {
        $this->authorize();
        $config = $this->config->get();
        $providers = [];
        foreach (SmsCatalog::IDS as $id) {
            $secrets = [];
            foreach (SmsCatalog::secrets($id) as $name) {
                $secrets[] = [
                    'name' => $name,
                    'set' => null !== $this->secrets->get($name),
                    'fixed' => $this->secrets->isFixed($name),
                ];
            }
            $providers[] = [
                'id' => $id,
                'configured' => self::configured($id, $config, $this->secrets),
                'secrets' => $secrets,
            ];
        }

        return ['config' => $config, 'providers' => $providers];
    }

    /**
     * @param array<string, string> $secrets new values by secret name; a name that is left out
     *     keeps its value and an empty one removes it.
     * @throws InvalidValue unknown_secret or secret_in_config; whatever SmsConfig rejects.
     */
    public function update(SmsConfig $config, array $secrets): void
    {
        $this->authorize();
        $known = SmsCatalog::allSecrets();
        foreach (\array_keys($secrets) as $name) {
            if (!\in_array($name, $known, true)) {
                throw new InvalidValue('unknown_secret', 'No provider has this secret.');
            }
            if ($this->secrets->isFixed($name)) {
                throw new InvalidValue('secret_in_config', 'This value is set in wp-config.php.');
            }
        }
        foreach ($secrets as $name => $value) {
            $this->secrets->set($name, $value);
        }
        $this->config->save($config);
    }

    /**
     * Sends a test message through the failover order, so it shows what a
     * customer would get.
     *
     * @return string "provider:reference"
     * @throws InvalidValue invalid_phone, or sms_failed with the providers' reasons.
     */
    public function test(string $phone, string $text): string
    {
        $this->authorize();
        try {
            return ($this->sender)()->send(PhoneNumber::fromInput($phone)->e164, $text);
        } catch (DeliveryFailed $e) {
            throw new InvalidValue('sms_failed', $e->getMessage());
        }
    }

    /**
     * A provider is usable when all its secrets are set and it has the sender
     * line it needs.
     */
    public static function configured(string $provider, SmsConfig $config, SmsSecrets $secrets): bool
    {
        foreach (SmsCatalog::secrets($provider) as $name) {
            if (null === $secrets->get($name)) {
                return false;
            }
        }

        return !SmsCatalog::needsSender($provider) || '' !== $config->sender($provider);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(NotificationAdminService::CAPABILITY)) {
            throw new Forbidden(NotificationAdminService::CAPABILITY);
        }
    }
}
