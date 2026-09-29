<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

/**
 * The SMS providers the plugin can talk to, and what each needs before it is
 * usable: the secrets (SecretStore names) and whether it needs a sender line.
 */
final class SmsCatalog
{
    /** Failover order until the owner sets one. */
    public const IDS = ['kavenegar', 'ippanel', 'smsir', 'melipayamak'];

    /**
     * @return list<string> SecretStore names.
     */
    public static function secrets(string $provider): array
    {
        return match ($provider) {
            'kavenegar' => ['sms_kavenegar_key'],
            'ippanel' => ['sms_ippanel_key'],
            'smsir' => ['sms_smsir_key'],
            'melipayamak' => ['sms_melipayamak_username', 'sms_melipayamak_password'],
            default => [],
        };
    }

    /**
     * Kavenegar sends from its default line when none is given.
     */
    public static function needsSender(string $provider): bool
    {
        return 'kavenegar' !== $provider;
    }

    /**
     * @return list<string> every secret name of every provider.
     */
    public static function allSecrets(): array
    {
        $names = [];
        foreach (self::IDS as $id) {
            $names = [...$names, ...self::secrets($id)];
        }

        return $names;
    }
}
