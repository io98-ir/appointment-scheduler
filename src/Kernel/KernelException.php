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

    public static function reservedModuleId(string $id): self
    {
        return new self(\sprintf('The module id "%s" is reserved for the kernel.', $id));
    }

    public static function invalidRateLimit(int $limit, int $windowSeconds): self
    {
        return new self(\sprintf(
            'A rate limit needs at least 1 attempt in at least 1 second, got %d in %d.',
            $limit,
            $windowSeconds
        ));
    }

    public static function unprotectedRoute(string $methods, string $path): self
    {
        return new self(\sprintf(
            'The route %s %s lets anyone in: that is allowed only for GET with a rate limit.',
            $methods,
            $path
        ));
    }

    public static function paginationArgsMissing(): self
    {
        return new self('page and per_page are not valid integers: register the route with Pagination::ARGS.');
    }

    public static function nestedTransaction(): self
    {
        return new self('A transaction is already open on this connection; MySQL would commit it silently.');
    }

    public static function updateWithoutWhere(string $table): self
    {
        return new self(\sprintf('An update of "%s" has no WHERE condition.', $table));
    }

    public static function secretInConfig(string $name): self
    {
        return new self(\sprintf(
            'The secret "%s" is defined in wp-config.php; a stored value would be ignored.',
            $name
        ));
    }

    public static function secretKeyLength(int $bytes): self
    {
        return new self(\sprintf('The secret store key must be 32 bytes, got %d.', $bytes));
    }
}
