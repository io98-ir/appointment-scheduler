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
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
