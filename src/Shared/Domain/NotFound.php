<?php

declare(strict_types=1);

namespace Vaqtyar\Shared\Domain;

/**
 * The item a use case was asked for does not exist, or is deleted. The REST
 * layer answers 404 with the error code (architecture §8).
 */
final class NotFound extends \DomainException
{
    /**
     * @param string $errorCode Stable, e.g. "location_not_found".
     */
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
