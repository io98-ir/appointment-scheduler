<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Catalog\Presentation\Rest;

use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Money;

/**
 * The parameters of a request, or one nested object of them, read as PHP
 * types. WordPress has checked the types against the route's schema, so a
 * wrong type here is a bug in the schema. A missing optional field takes
 * the given default: WordPress fills defaults for top-level parameters
 * only, not for fields of nested objects.
 */
final class Input
{
    /**
     * @param array<mixed> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @param \WP_REST_Request<array<string, mixed>> $request
     */
    public static function fromRequest(\WP_REST_Request $request): self
    {
        return new self($request->get_params());
    }

    public function string(string $key, ?string $default = null): string
    {
        $value = $this->values[$key] ?? $default;

        return \is_string($value) ? $value : throw self::wrongType($key);
    }

    /**
     * A form sends an empty field as "", which reads as none.
     */
    public function stringOrNull(string $key): ?string
    {
        $value = $this->values[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw self::wrongType($key);
        }

        return null === $value || '' === \trim($value) ? null : $value;
    }

    public function int(string $key, ?int $default = null): int
    {
        $value = $this->values[$key] ?? $default;

        return \is_int($value) ? $value : throw self::wrongType($key);
    }

    public function intOrNull(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        return null === $value || \is_int($value) ? $value : throw self::wrongType($key);
    }

    public function bool(string $key, ?bool $default = null): bool
    {
        $value = $this->values[$key] ?? $default;

        return \is_bool($value) ? $value : throw self::wrongType($key);
    }

    /**
     * {"amount": int, "currency": "IRR"}, the API's money shape (architecture §9).
     */
    public function money(string $key): Money
    {
        return $this->moneyOrNull($key) ?? throw self::wrongType($key);
    }

    public function moneyOrNull(string $key): ?Money
    {
        $value = $this->values[$key] ?? null;
        if (null === $value) {
            return null;
        }

        return \is_array($value) ? Money::fromArray($value) : throw self::wrongType($key);
    }

    /**
     * @return list<self> The objects of a list field; none when it is missing.
     */
    public function objects(string $key): array
    {
        $value = $this->values[$key] ?? [];
        if (!\is_array($value)) {
            throw self::wrongType($key);
        }

        return \array_map(
            static fn (mixed $item): self => \is_array($item) ? new self($item) : throw self::wrongType($key),
            \array_values($value)
        );
    }

    /**
     * An id that must name something WordPress stores.
     *
     * @param \Closure(int): bool $exists
     */
    public function wpIdOrNull(string $key, \Closure $exists, string $errorCode): ?int
    {
        $id = $this->intOrNull($key);
        if (null !== $id && ($id < 1 || !$exists($id))) {
            throw new InvalidValue($errorCode, 'The id does not name a stored item of the right kind.');
        }

        return $id;
    }

    private static function wrongType(string $key): \LogicException
    {
        return new \LogicException("The schema let a wrong type through for {$key}.");
    }
}
