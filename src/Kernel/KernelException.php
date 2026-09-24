<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * A wiring mistake in the plugin itself, never a runtime condition to recover from.
 */
final class KernelException extends \LogicException
{
    public static function serviceNotFound(string $id): self
    {
        return new self(\sprintf('No service is registered for "%s".', $id));
    }

    public static function serviceAlreadyRegistered(string $id): self
    {
        return new self(\sprintf('A service is already registered for "%s".', $id));
    }

    public static function wrongServiceType(string $id, object $service): self
    {
        return new self(\sprintf('The factory for "%s" returned %s.', $id, $service::class));
    }

    /**
     * @param list<string> $chain
     */
    public static function circularDependency(array $chain): self
    {
        return new self(\sprintf('Circular dependency: %s.', \implode(' -> ', $chain)));
    }

    public static function invalidName(string $kind, string $name, string $rule): self
    {
        return new self(\sprintf('Invalid %s name "%s": %s.', $kind, $name, $rule));
    }

    public static function nameTooLong(string $kind, string $name, int $max): self
    {
        return new self(\sprintf('The %s name "%s" is longer than %d characters.', $kind, $name, $max));
    }

    public static function wpdbUnavailable(): self
    {
        return new self('$wpdb is not available; table names can only be built once WordPress has loaded.');
    }

    public static function moduleAlreadyRegistered(string $id): self
    {
        return new self(\sprintf('A module with the id "%s" is already registered.', $id));
    }
}
