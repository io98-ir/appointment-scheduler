<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Infrastructure\Sms;

use Vaqtyar\Modules\Notifications\Application\SmsAdminService;
use Vaqtyar\Modules\Notifications\Application\SmsConfig;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsProvider;
use Vaqtyar\Modules\Notifications\Application\SmsSecrets;

/**
 * The providers that are set up, in the owner's failover order: a listed
 * provider whose secrets or sender line are missing is left out.
 */
final class SmsProviders
{
    /**
     * @return list<SmsProvider>
     */
    public static function build(SmsConfig $config, SmsSecrets $secrets, SmsHttp $http): array
    {
        $providers = [];
        foreach ($config->order as $id) {
            if (!SmsAdminService::configured($id, $config, $secrets)) {
                continue;
            }
            $sender = $config->sender($id);
            $provider = match ($id) {
                'kavenegar' => new KavenegarProvider($http, $secrets->get('sms_kavenegar_key') ?? '', $sender),
                'ippanel' => new IpPanelProvider($http, $secrets->get('sms_ippanel_key') ?? '', $sender),
                'smsir' => new SmsIrProvider($http, $secrets->get('sms_smsir_key') ?? '', $sender),
                'melipayamak' => new MelipayamakProvider(
                    $http,
                    $secrets->get('sms_melipayamak_username') ?? '',
                    $secrets->get('sms_melipayamak_password') ?? '',
                    $sender
                ),
                default => null,
            };
            if (null !== $provider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }
}
