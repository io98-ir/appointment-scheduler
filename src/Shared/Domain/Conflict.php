<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The request was valid but the state no longer allows it, e.g. a slot
 * taken since it was offered. The REST layer answers 409.
 */
final class Conflict extends \DomainException
{
    /**
     * @param string $errorCode Stable, e.g. "slot_taken".
     */
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
