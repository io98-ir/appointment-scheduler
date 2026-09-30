<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong -- a translatable string is not split.

namespace Vaqtyar\Modules\Admin\Presentation;

use Vaqtyar\Kernel\Identity;
use Vaqtyar\Modules\Admin\Domain\HealthCheck;
use Vaqtyar\Modules\Admin\Domain\HealthStatus;

/**
 * The words for a HealthCheck, shared by the status screen and Site Health.
 * The label names the check; the description says what the outcome means and
 * what to do about it.
 */
final class HealthText
{
    /**
     * @return array{label: string, description: string}
     */
    public static function of(HealthCheck $check): array
    {
        $ok = HealthStatus::Good === $check->status;
        $name = Identity::NAME;

        return match ($check->id) {
            'database_engine' => [
                'label' => $ok
                    ? \__('The database uses InnoDB', 'vaqtyar')
                    : \__('The database does not use InnoDB', 'vaqtyar'),
                'description' => $ok
                    ? \__('Bookings are written in transactions with row locks, which InnoDB provides.', 'vaqtyar')
                    : \__('Without InnoDB two customers could book the same time. Ask your host to convert the plugin tables to InnoDB.', 'vaqtyar'),
            ],
            'sodium' => [
                'label' => $ok
                    ? \__('The sodium extension is available', 'vaqtyar')
                    : \__('The sodium extension is missing', 'vaqtyar'),
                'description' => $ok
                    ? \__('Gateway and SMS keys are stored encrypted.', 'vaqtyar')
                    : \__('Gateway and SMS keys cannot be stored safely. Ask your host to enable PHP sodium.', 'vaqtyar'),
            ],
            'intl' => [
                'label' => $ok
                    ? \__('The intl extension is available', 'vaqtyar')
                    : \__('The intl extension is missing', 'vaqtyar'),
                'description' => $ok
                    ? \__('Names and numbers can be formatted for every locale.', 'vaqtyar')
                    : \__('The plugin works without it, but enabling PHP intl gives better formatting of names and numbers.', 'vaqtyar'),
            ],
            'timezone_data' => [
                'label' => $ok
                    ? \__('The time zone data is current for Iran', 'vaqtyar')
                    : \__('The time zone data is out of date for Iran', 'vaqtyar'),
                'description' => $ok
                    ? \__('Iran has had no daylight saving time since 2023, and this server agrees.', 'vaqtyar')
                    : \__('This server still applies daylight saving time to Iran, or does not know the zone. Local times would shift by an hour. Ask your host to update the tzdata package or PHP.', 'vaqtyar'),
            ],
            'cron' => [
                'label' => $ok
                    ? \__('WordPress cron runs from the system', 'vaqtyar')
                    : \__('WordPress cron runs on page visits', 'vaqtyar'),
                'description' => $ok
                    ? \__('Reminders and reservation expiry run on time.', 'vaqtyar')
                    : \sprintf(
                        /* translators: %s: plugin name */
                        \__('%s sends reminders and releases unpaid reservations from the job queue. On a quiet site page visits are too rare to run it on time: define DISABLE_WP_CRON and call wp-cron.php every minute from the system cron.', 'vaqtyar'),
                        $name
                    ),
            ],
            'queue' => [
                'label' => match ($check->status) {
                    HealthStatus::Good => \__('The job queue is healthy', 'vaqtyar'),
                    HealthStatus::Recommended => \__('Some jobs failed recently', 'vaqtyar'),
                    HealthStatus::Critical => \__('The job queue is stalled', 'vaqtyar'),
                },
                'description' => match ($check->status) {
                    HealthStatus::Good => \__('No job is overdue and none failed in the last week.', 'vaqtyar'),
                    HealthStatus::Recommended => \__('Look at the recent errors on the status screen, and at Tools, Scheduled Actions, Failed.', 'vaqtyar'),
                    HealthStatus::Critical => \__('Jobs are waiting far past their time, so reminders and notifications are late. Check that WordPress cron runs.', 'vaqtyar'),
                },
            ],
            default => ['label' => $check->id, 'description' => ''],
        };
    }
}
