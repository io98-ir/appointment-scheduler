<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Application;

use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;

/**
 * The admin's use cases for the site-wide booking rules. Each call checks the
 * capability again, after the REST permission callback (architecture §12).
 */
final class BookingRulesService
{
    public const CAPABILITY = ScheduleService::CAPABILITY;

    public function __construct(private readonly Authorizer $authorizer, private readonly BookingRulesStore $store)
    {
    }

    public function rules(): BookingRules
    {
        $this->authorize();

        return $this->store->rules();
    }

    public function save(BookingRules $rules): BookingRules
    {
        $this->authorize();
        $this->store->save($rules);

        return $rules;
    }

    private function authorize(): void
    {
        if (!$this->authorizer->allows(self::CAPABILITY)) {
            throw new Forbidden(self::CAPABILITY);
        }
    }
}
