<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Presentation\Rest;

use Vaqtyar\Kernel\Caps;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Modules\Scheduling\Application\BookingRulesService;
use Vaqtyar\Modules\Scheduling\Domain\Availability\BookingRules;
use Vaqtyar\Modules\Scheduling\Domain\Availability\StaffChoice;

/**
 * The site-wide booking rules (docs/api.md):
 *
 *     GET /booking-rules    {slot_step_min, min_notice_min, max_advance_days, staff_choice}
 *     PUT /booking-rules    replace them
 */
final class BookingRulesRoutes
{
    /**
     * @param \Closure(): BookingRulesService $service Built when a request needs it.
     */
    public function __construct(private readonly Router $router, private readonly \Closure $service)
    {
    }

    public function register(): void
    {
        $allowed = static fn (): bool => \current_user_can(Caps::name(BookingRulesService::CAPABILITY));

        $this->router->add(
            '/booking-rules',
            'GET',
            fn (): array => self::toJson(($this->service)()->rules()),
            $allowed
        );
        $this->router->add(
            '/booking-rules',
            'PUT',
            function (\WP_REST_Request $request): array {
                $p = $request->get_params();

                return self::toJson(($this->service)()->save(BookingRules::of(
                    self::int($p['slot_step_min'] ?? null),
                    self::int($p['min_notice_min'] ?? null),
                    self::int($p['max_advance_days'] ?? null),
                    StaffChoice::tryFrom(self::string($p['staff_choice'] ?? null)) ?? StaffChoice::LeastBusy
                )));
            },
            $allowed,
            [
                'slot_step_min' => ['type' => 'integer', 'required' => true],
                'min_notice_min' => ['type' => 'integer', 'required' => true],
                'max_advance_days' => ['type' => 'integer', 'required' => true],
                'staff_choice' => ['type' => 'string', 'enum' => ['least_busy', 'priority'], 'default' => 'least_busy'],
            ]
        );
    }

    /**
     * @return array{slot_step_min: int, min_notice_min: int, max_advance_days: int, staff_choice: string}
     */
    private static function toJson(BookingRules $rules): array
    {
        return [
            'slot_step_min' => $rules->slotStepMin,
            'min_notice_min' => $rules->minNoticeMin,
            'max_advance_days' => $rules->maxAdvanceDays,
            'staff_choice' => $rules->staffChoice->value,
        ];
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) ? $value : 0;
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
