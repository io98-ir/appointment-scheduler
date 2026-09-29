<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Module;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Notifications\Application\NotificationAdminService;
use Vaqtyar\Modules\Notifications\Application\NotificationChannel;
use Vaqtyar\Modules\Notifications\Application\NotificationLog;
use Vaqtyar\Modules\Notifications\Application\NotificationService;
use Vaqtyar\Modules\Notifications\Application\ReminderScheduler;
use Vaqtyar\Modules\Notifications\Application\TemplateRepository;
use Vaqtyar\Modules\Notifications\Domain\Preferences;
use Vaqtyar\Modules\Notifications\Domain\QuietHours;
use Vaqtyar\Modules\Notifications\Domain\TemplateRenderer;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Modules\Notifications\Infrastructure\ActionSchedulerReminders;
use Vaqtyar\Modules\Notifications\Infrastructure\DateFormatterPresenter;
use Vaqtyar\Modules\Notifications\Infrastructure\EmailChannel;
use Vaqtyar\Modules\Notifications\Infrastructure\Migrations\CreateNotificationTables;
use Vaqtyar\Modules\Notifications\Infrastructure\NotificationSettings;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbNotificationLog;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbTemplateRepository;
use Vaqtyar\Modules\Notifications\Presentation\Rest\NotificationRoutes;
use Vaqtyar\Shared\DateFormatter;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Notifications (architecture §3): templates by trigger, audience and
 * channel, sent from the booking events, with reminders scheduled at
 * start minus an offset. Booking and this module meet through Booking's
 * jobs (`{prefix}/booking/appointment_booked`, `_cancelled` and
 * `_rescheduled`, queued in the booking's own transaction) and its
 * AppointmentFactsReader contract. More channels (SMS, T5.5) are added on
 * the `{prefix}/notifications/channels` filter.
 */
final class NotificationsModule implements Module
{
    public function id(): string
    {
        return 'notifications';
    }

    public function register(Container $container): void
    {
        $container->singleton(
            TemplateRepository::class,
            static fn (Container $c) => new WpdbTemplateRepository($c->get(Db::class), $c->get(Clock::class))
        );
        $container->singleton(
            NotificationLog::class,
            static fn (Container $c) => new WpdbNotificationLog($c->get(Db::class))
        );
        $container->singleton(ReminderScheduler::class, static fn () => new ActionSchedulerReminders());
        $container->singleton(NotificationService::class, static function (Container $c): NotificationService {
            $settings = $c->get(Settings::class);

            return new NotificationService(
                $c->get(AppointmentFactsReader::class),
                $c->get(TemplateRepository::class),
                $c->get(NotificationLog::class),
                $c->get(ReminderScheduler::class),
                new DateFormatterPresenter($c->get(DateFormatter::class)),
                new TemplateRenderer(),
                $c->get(Clock::class),
                self::channels(),
                static function () use ($settings): Preferences {
                    $stored = $settings->get(NotificationSettings::class);
                    $admin = '' !== $stored->adminEmail ? $stored->adminEmail : \get_option('admin_email');

                    return new Preferences(
                        \is_string($admin) ? $admin : '',
                        new QuietHours($stored->quietFromMin, $stored->quietToMin)
                    );
                }
            );
        });
        $container->singleton(NotificationAdminService::class, static fn (Container $c) => new NotificationAdminService(
            new WpAuthorizer(),
            $c->get(TemplateRepository::class),
            $c->get(NotificationLog::class),
            \array_keys(self::channels())
        ));
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateNotificationTables()];
    }

    /**
     * @return array<string, list<string>>
     */
    public function capabilities(): array
    {
        return [NotificationAdminService::CAPABILITY => ['administrator']];
    }

    public function boot(Context $context): void
    {
        $container = $context->container;
        foreach (
            [
            'booking/appointment_booked' => Trigger::Booked,
            'booking/appointment_cancelled' => Trigger::Cancelled,
            'booking/appointment_rescheduled' => Trigger::Rescheduled,
            ] as $hook => $trigger
        ) {
            \add_action(Hooks::name($hook), static function (mixed $appointmentId) use ($container, $trigger): void {
                if (\is_numeric($appointmentId)) {
                    $container->get(NotificationService::class)->appointmentChanged($trigger, (int) $appointmentId);
                }
            });
        }
        \add_action(
            ActionSchedulerReminders::hook(),
            static function (mixed $appointmentId, mixed $templateId, mixed $start) use ($container): void {
                if (\is_numeric($appointmentId) && \is_numeric($templateId) && \is_numeric($start)) {
                    $container->get(NotificationService::class)->remind(
                        (int) $appointmentId,
                        (int) $templateId,
                        (int) $start
                    );
                }
            },
            10,
            3
        );
        \add_action('rest_api_init', static function () use ($container): void {
            (new NotificationRoutes(
                $container->get(Router::class),
                static fn (): NotificationAdminService => $container->get(NotificationAdminService::class)
            ))->register();
        });
    }

    /**
     * @return array<string, NotificationChannel> by id.
     */
    private static function channels(): array
    {
        $channels = \apply_filters(Hooks::name('notifications/channels'), [new EmailChannel()]);
        $byId = [];
        foreach (\is_array($channels) ? $channels : [] as $channel) {
            if ($channel instanceof NotificationChannel) {
                $byId[$channel->id()] = $channel;
            }
        }

        return $byId;
    }
}
