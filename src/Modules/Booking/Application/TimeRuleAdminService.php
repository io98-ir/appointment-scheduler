<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Booking\Application;

use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRuleDefinition;
use Vaqtyar\Modules\Booking\Domain\Pricing\TimeRuleRepository;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The admin's time-rule use cases (T3.5): CRUD on the price_rules rows of
 * type "time". The same capability as booking. TimeRule's own constructor
 * checks the weekdays, the time range, the dates and the percent.
 */
final class TimeRuleAdminService
{
    public const CAPABILITY = BookingService::CAPABILITY;

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly CatalogApi $catalog,
        private readonly TimeRuleRepository $rules,
    ) {
    }

    /**
     * @return list<TimeRuleDefinition>
     */
    public function all(): array
    {
        $this->authorize();

        return $this->rules->all();
    }

    /**
     * @throws NotFound time_rule_not_found for an unknown id, or service_not_found for an unknown service.
     */
    public function save(TimeRuleDefinition $definition): TimeRuleDefinition
    {
        $this->authorize();
        if (null !== $definition->serviceId && !$this->catalog->isStored('service', $definition->serviceId)) {
            throw new NotFound('service_not_found', 'No service has this id.');
        }
        if (0 !== $definition->id) {
            $this->rules->find($definition->id) ?? throw self::notFound();
        }

        return $this->rules->save($definition);
    }

    public function delete(int $id): void
    {
        $this->authorize();
        $this->rules->find($id) ?? throw self::notFound();
        $this->rules->delete($id);
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }

    private static function notFound(): NotFound
    {
        return new NotFound('time_rule_not_found', 'No time rule has this id.');
    }
}
