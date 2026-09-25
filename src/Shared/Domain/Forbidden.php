<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The current user may not run this use case. Thrown by the Application
 * layer, which checks authorization again after the REST permission callback
 * (architecture §12); the REST layer answers 403.
 */
final class Forbidden extends \DomainException
{
    public function __construct(public readonly string $capability)
    {
        parent::__construct('The current user lacks a capability this use case needs.');
    }
}
