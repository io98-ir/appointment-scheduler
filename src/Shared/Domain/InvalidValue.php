<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * A value that breaks a value object's rules, usually from user input.
 *
 * The Domain returns an error code, never a translated text: the
 * Presentation layer maps the code to a message (principles §3). The
 * message is for developers and never contains the rejected input.
 */
final class InvalidValue extends \DomainException
{
    /**
     * @param array<string, scalar> $details What the client needs to point at the culprit,
     *     e.g. the field_key of a custom field; never the rejected input.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
