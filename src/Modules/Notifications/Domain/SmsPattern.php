<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications\Domain;

use Vaqtyar\Shared\Domain\InvalidValue;

/**
 * A provider's approved pattern (template) for one message: its code and
 * the placeholders that fill its variables, in order. Most Iranian lines
 * deliver only pattern messages reliably, and each provider keeps its own
 * pattern codes, so a template holds one per provider.
 *
 * A provider that names variables (IPPanel, SMS.ir) is given the placeholder
 * names, so the pattern's variables are named the same: {customer_name} fills
 * a variable called customer_name. One that numbers them (Kavenegar,
 * Melipayamak) gets the values in this order.
 */
final class SmsPattern
{
    public const MAX_ARGS = 10;

    /**
     * @param list<string> $args placeholder names, each once.
     * @throws InvalidValue invalid_sms_pattern
     */
    public function __construct(public readonly string $code, public readonly array $args)
    {
        if (1 !== \preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $code)) {
            throw new InvalidValue('invalid_sms_pattern', 'A pattern code is letters, digits, - and _.');
        }
        if (\count($args) > self::MAX_ARGS || \count(\array_unique($args)) !== \count($args)) {
            throw new InvalidValue('invalid_sms_pattern', 'A pattern has at most 10 different values.');
        }
        foreach ($args as $name) {
            if (1 !== \preg_match('/^[a-z_]{1,32}$/D', $name)) {
                throw new InvalidValue('invalid_sms_pattern', 'A pattern value is a placeholder name.');
            }
        }
    }

    /**
     * @param array<string, string> $values placeholder name to text.
     * @return array<string, string> the pattern's values by name, in order; a missing one is empty.
     */
    public function fill(array $values): array
    {
        $filled = [];
        foreach ($this->args as $name) {
            $filled[$name] = $values[$name] ?? '';
        }

        return $filled;
    }
}
