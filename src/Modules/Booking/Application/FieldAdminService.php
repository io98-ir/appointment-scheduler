<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Field\FieldDefinition;
use Vaqtyar\Modules\Booking\Domain\Field\FieldRepository;
use Vaqtyar\Modules\Booking\Domain\Field\FieldScope;
use Vaqtyar\Modules\Booking\Domain\Field\FieldSetValidator;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's custom-field use cases (implementation-notes §4.13): CRUD on
 * the global fields, or a service's own. The same capability as booking,
 * not a new one. Every save is checked against the "available set" the
 * field would join at booking time (FieldSetValidator), so a duplicate key
 * or a broken show_if is rejected here rather than silently misbehaving
 * later.
 */
final class FieldAdminService
{
    public const CAPABILITY = BookingService::CAPABILITY;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CatalogApi $catalog,
        private readonly FieldRepository $fields,
    ) {
    }

    /**
     * @return list<FieldDefinition>
     */
    public function globalFields(): array
    {
        $this->authorize();

        return $this->fields->globalFields();
    }

    /**
     * @return list<FieldDefinition>
     */
    public function forService(int $serviceId): array
    {
        $this->authorize();
        $this->assertService($serviceId);

        return $this->fields->forService($serviceId);
    }

    public function save(FieldDefinition $definition): FieldDefinition
    {
        $this->authorize();
        if (FieldScope::Service === $definition->scope) {
            $this->assertService(self::serviceId($definition));
        }
        if (null !== $definition->id) {
            $this->fields->find($definition->id) ?? throw self::notFound();
        }
        FieldSetValidator::validate(self::replacing($this->availableSet($definition), $definition));

        return $this->fields->save($definition);
    }

    public function delete(int $id): void
    {
        $this->authorize();
        $this->fields->find($id) ?? throw self::notFound();
        $this->fields->delete($id);
    }

    /**
     * @return list<FieldDefinition> the set this field would join at
     *     booking time: the globals, and the service's own for a
     *     service-scoped field (FieldReader::forService()'s shape).
     */
    private function availableSet(FieldDefinition $definition): array
    {
        $globals = $this->fields->globalFields();
        if (FieldScope::Global === $definition->scope) {
            return $globals;
        }

        return [...$globals, ...$this->fields->forService(self::serviceId($definition))];
    }

    /**
     * A service-scoped definition always has a service id (FieldDefinition's
     * own constructor guarantees it); this just narrows the type for callers.
     */
    private static function serviceId(FieldDefinition $definition): int
    {
        return $definition->serviceId ?? throw new \LogicException('A service field always has a service id.');
    }

    /**
     * @param list<FieldDefinition> $set
     * @return list<FieldDefinition>
     */
    private static function replacing(array $set, FieldDefinition $definition): array
    {
        $kept = null === $definition->id
            ? $set
            : \array_values(\array_filter($set, static fn (FieldDefinition $d): bool => $d->id !== $definition->id));

        return [...$kept, $definition];
    }

    private function assertService(int $serviceId): void
    {
        if (!$this->catalog->isStored('service', $serviceId)) {
            throw new NotFound('service_not_found', 'No service has this id.');
        }
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }

    private static function notFound(): NotFound
    {
        return new NotFound('field_not_found', 'No field has this id.');
    }
}
