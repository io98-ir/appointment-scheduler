<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Application;

use Vaqtyar\Modules\Notifications\Domain\SmsNumber;
use Vaqtyar\Modules\Notifications\Domain\SmsPattern;

/**
 * Sends one SMS through the providers in failover order: the first that
 * accepts it wins. A provider with a pattern for the message sends by that
 * pattern, and one without sends the plain text.
 */
final class SmsSender
{
    /**
     * @param list<SmsProvider> $providers in failover order.
     */
    public function __construct(private readonly array $providers)
    {
    }

    public function isConfigured(): bool
    {
        return [] !== $this->providers;
    }

    /**
     * @param string $phone E.164.
     * @param array<string, SmsPattern> $patterns by provider id.
     * @param array<string, string> $values placeholder values for the patterns.
     * @return string "provider:reference" of the provider that took it.
     * @throws DeliveryFailed when no provider is set up, the number is not an Iranian mobile,
     *     or every provider refused.
     */
    public function send(string $phone, string $text, array $patterns = [], array $values = []): string
    {
        $mobile = SmsNumber::local($phone);
        if (null === $mobile) {
            throw new DeliveryFailed('The SMS providers reach Iranian mobile numbers only.');
        }
        $reasons = [];
        foreach ($this->providers as $provider) {
            $pattern = $patterns[$provider->id()] ?? null;
            try {
                $reference = null === $pattern
                    ? $provider->send($mobile, $text)
                    : $provider->sendPattern($mobile, $pattern->code, $pattern->fill($values));

                return $provider->id() . ':' . $reference;
            } catch (DeliveryFailed $e) {
                $reasons[] = $provider->id() . ': ' . $e->getMessage();
            }
        }

        throw new DeliveryFailed([] === $reasons ? 'No SMS provider is set up.' : \implode('; ', $reasons));
    }
}
