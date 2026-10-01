<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Notifications;

use Vaqtyar\Kernel\Container;
use Vaqtyar\Kernel\Context;
use Vaqtyar\Kernel\Database\Db;
use Vaqtyar\Kernel\Hooks;
use Vaqtyar\Kernel\Log\Logger;
use Vaqtyar\Kernel\Rest\Router;
use Vaqtyar\Kernel\SecretStore;
use Vaqtyar\Kernel\Settings\Settings;
use Vaqtyar\Kernel\Switchable;
use Vaqtyar\Modules\Booking\Contracts\AppointmentFactsReader;
use Vaqtyar\Modules\Customers\Contracts\CustomerDirectory;
use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\NotificationAdminService;
use Vaqtyar\Modules\Notifications\Application\NotificationChannel;
use Vaqtyar\Modules\Notifications\Application\NotificationLog;
use Vaqtyar\Modules\Notifications\Application\NotificationService;
use Vaqtyar\Modules\Notifications\Application\OtpSms;
use Vaqtyar\Modules\Notifications\Application\ReminderScheduler;
use Vaqtyar\Modules\Notifications\Application\SmsAdminService;
use Vaqtyar\Modules\Notifications\Application\SmsChannel;
use Vaqtyar\Modules\Notifications\Application\SmsConfigStore;
use Vaqtyar\Modules\Notifications\Application\SmsHttp;
use Vaqtyar\Modules\Notifications\Application\SmsSecrets;
use Vaqtyar\Modules\Notifications\Application\SmsSender;
use Vaqtyar\Modules\Notifications\Application\TemplateRepository;
use Vaqtyar\Modules\Notifications\Application\WaitlistSms;
use Vaqtyar\Modules\Notifications\Domain\Preferences;
use Vaqtyar\Modules\Notifications\Domain\QuietHours;
use Vaqtyar\Modules\Notifications\Domain\TemplateRenderer;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Modules\Notifications\Infrastructure\ActionSchedulerReminders;
use Vaqtyar\Modules\Notifications\Infrastructure\DateFormatterPresenter;
use Vaqtyar\Modules\Notifications\Infrastructure\EmailChannel;
use Vaqtyar\Modules\Notifications\Infrastructure\Migrations\AddSmsPatterns;
use Vaqtyar\Modules\Notifications\Infrastructure\Migrations\CreateNotificationTables;
use Vaqtyar\Modules\Notifications\Infrastructure\NotificationSettings;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbNotificationLog;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\WpdbTemplateRepository;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SecretStoreSmsSecrets;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SettingsSmsConfigStore;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\SmsProviders;
use Vaqtyar\Modules\Notifications\Infrastructure\Sms\WpSmsHttp;
use Vaqtyar\Modules\Notifications\Presentation\Rest\NotificationRoutes;
use Vaqtyar\Modules\Notifications\Presentation\Rest\SmsRoutes;
use Vaqtyar\Shared\DateFormatter;
use Vaqtyar\Shared\Domain\Clock;
use Vaqtyar\Shared\WpAuthorizer;

/**
 * Notifications (architecture §3): templates by trigger, audience and
 * channel, sent from the booking events, with reminders scheduled at
 * start minus an offset. Booking and this module meet through Booking's
 * jobs (`{prefix}/booking/appointment_booked`, `_cancelled` and
 * `_rescheduled`, queued in the booking's own transaction) and its
 * AppointmentFactsReader contract. The sms channel exists once an SMS
 * provider is set up (T5.5), and the module sends the customers module's
 * login code (`{prefix}/customers/otp`) the same way. More channels are
 * added on the `{prefix}/notifications/channels` filter.
 */
final class NotificationsModule implements Switchable
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
                self::channels($c),
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
            \array_keys(self::channels($c))
        ));
        $container->singleton(SmsHttp::class, static fn () => new WpSmsHttp());
        $container->singleton(
            SmsSecrets::class,
            static fn (Container $c) => new SecretStoreSmsSecrets($c->get(SecretStore::class))
        );
        $container->singleton(
            SmsConfigStore::class,
            static fn (Container $c) => new SettingsSmsConfigStore($c->get(Settings::class))
        );
        $container->singleton(SmsAdminService::class, static fn (Container $c) => new SmsAdminService(
            new WpAuthorizer(),
            $c->get(SmsConfigStore::class),
            $c->get(SmsSecrets::class),
            static fn (): SmsSender => self::smsSender($c)
        ));
        $container->singleton(OtpSms::class, static fn (Container $c) => new OtpSms(
            static fn (): SmsSender => self::smsSender($c),
            $c->get(SmsConfigStore::class)
        ));
        $container->singleton(WaitlistSms::class, static fn (Container $c) => new WaitlistSms(
            static fn (): SmsSender => self::smsSender($c),
            $c->get(CustomerDirectory::class),
            static fn (int $start): string => $c->get(DateFormatter::class)->longDate(
                new \DateTimeImmutable('@' . $start),
                \wp_timezone()
            )
        ));
    }

    /**
     * @return list<\Vaqtyar\Kernel\Database\Migration>
     */
    public function migrations(): array
    {
        return [new CreateNotificationTables(), new AddSmsPatterns()];
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
        // The login code: the customers module only announces it (architecture §3).
        \add_action(Hooks::name('customers/otp'), static function (mixed $phone, mixed $code) use ($container): void {
            if (!\is_string($phone) || !\is_string($code)) {
                return;
            }
            try {
                $container->get(OtpSms::class)->send($phone, $code);
            } catch (DeliveryFailed $e) {
                // The code stays out of the log; the customer asks for another.
                $container->get(Logger::class)->error('sms', 'The login code could not be sent.', [
                    'reason' => $e->getMessage(),
                ]);
            }
        }, 10, 2);
        // A time opened for a customer on the waiting list: the booking module only announces it.
        \add_action(
            Hooks::name('waitlist/slot_opened'),
            static function (
                mixed $customerId,
                mixed $day,
                mixed $start,
                mixed $variant,
                mixed $pageUrl
            ) use ($container): void {
                if (!\is_int($customerId) || !\is_int($start) || !\is_string($pageUrl)) {
                    return;
                }
                try {
                    $container->get(WaitlistSms::class)->send($customerId, $start, $pageUrl);
                } catch (DeliveryFailed $e) {
                    $container->get(Logger::class)->error('sms', 'The waiting-list message could not be sent.', [
                        'customer_id' => $customerId,
                        'reason' => $e->getMessage(),
                    ]);
                }
            },
            10,
            5
        );
        \add_action('rest_api_init', static function () use ($container): void {
            $router = $container->get(Router::class);
            (new NotificationRoutes(
                $router,
                static fn (): NotificationAdminService => $container->get(NotificationAdminService::class)
            ))->register();
            (new SmsRoutes(
                $router,
                static fn (): SmsAdminService => $container->get(SmsAdminService::class)
            ))->register();
        });
    }

    /**
     * A new sender, built from what is set up now.
     */
    private static function smsSender(Container $c): SmsSender
    {
        return new SmsSender(SmsProviders::build(
            $c->get(SmsConfigStore::class)->get(),
            $c->get(SmsSecrets::class),
            $c->get(SmsHttp::class)
        ));
    }

    /**
     * @return array<string, NotificationChannel> by id. The sms channel is there once a provider is set up.
     */
    private static function channels(Container $c): array
    {
        $own = [new EmailChannel()];
        $sms = self::smsSender($c);
        if ($sms->isConfigured()) {
            $own[] = new SmsChannel($sms);
        }
        $channels = \apply_filters(Hooks::name('notifications/channels'), $own);
        $byId = [];
        foreach (\is_array($channels) ? $channels : [] as $channel) {
            if ($channel instanceof NotificationChannel) {
                $byId[$channel->id()] = $channel;
            }
        }

        return $byId;
    }
}
