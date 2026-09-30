<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Admin\Presentation;

use Vaqtyar\Kernel\Identity;
use Vaqtyar\Modules\Admin\Application\StatusSource;
use Vaqtyar\Modules\Admin\Domain\HealthCheck;
use Vaqtyar\Modules\Admin\Domain\HealthEvaluator;
use Vaqtyar\Modules\Admin\Domain\HealthStatus;

/**
 * The health checks as WordPress Site Health tests (Tools, Site Health).
 * They read the server, not the user's data, so they need no capability of
 * ours: Site Health itself is for administrators.
 */
final class SiteHealthTests
{
    private const BADGE_COLORS = [
        HealthStatus::Good->value => 'green',
        HealthStatus::Recommended->value => 'orange',
        HealthStatus::Critical->value => 'red',
    ];

    public function __construct(private readonly StatusSource $source, private readonly HealthEvaluator $evaluator)
    {
    }

    /**
     * The `site_status_tests` filter.
     *
     * @param array<string, mixed> $tests
     * @return array<string, mixed>
     */
    public function register(array $tests): array
    {
        $direct = isset($tests['direct']) && \is_array($tests['direct']) ? $tests['direct'] : [];
        foreach (HealthEvaluator::IDS as $id) {
            $direct[Identity::PREFIX . '_' . $id] = [
                'label' => Identity::NAME . ' ' . $id,
                'test' => fn (): array => $this->run($id),
            ];
        }
        $tests['direct'] = $direct;

        return $tests;
    }

    /**
     * @return array<string, mixed> The shape Site Health expects from a direct test.
     */
    private function run(string $id): array
    {
        $check = $this->find($id);
        $text = HealthText::of($check);

        return [
            'label' => $text['label'],
            'status' => $check->status->value,
            'badge' => ['label' => Identity::NAME, 'color' => self::BADGE_COLORS[$check->status->value]],
            'description' => '<p>' . \esc_html($text['description']) . '</p>',
            'actions' => '',
            'test' => Identity::PREFIX . '_' . $id,
        ];
    }

    private function find(string $id): HealthCheck
    {
        foreach ($this->evaluator->evaluate($this->source->facts()) as $check) {
            if ($check->id === $id) {
                return $check;
            }
        }

        throw new \LogicException("Unknown health check {$id}.");
    }
}
