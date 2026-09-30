<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Integration\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Notifications\Application\DeliveryFailed;
use Vaqtyar\Modules\Notifications\Application\Message;
use Vaqtyar\Modules\Notifications\Infrastructure\EmailChannel;

/**
 * The email channel against the real wp_mail(): whatever the site does to
 * the content type, our message leaves as plain text, so a customer's name
 * with markup in it stays text.
 */
final class EmailChannelTest extends TestCase
{
    /** @var list<string> */
    private array $contentTypes = [];

    protected function tearDown(): void
    {
        \remove_all_filters('wp_mail_content_type');
        \remove_all_actions('phpmailer_init');
        parent::tearDown();
    }

    public function testASiteWideHtmlMailFilterDoesNotTurnTheBodyIntoHtml(): void
    {
        // What an "HTML email" plugin does to every message of the site.
        \add_filter('wp_mail_content_type', static fn (): string => 'text/html');
        // Nothing is sent: the mailer is stopped once WordPress has set it up. The action runs
        // outside wp_mail()'s own try block, so the exception comes out of send().
        \add_action('phpmailer_init', function (\PHPMailer\PHPMailer\PHPMailer $mailer): void {
            $this->contentTypes[] = $mailer->ContentType;

            throw new \PHPMailer\PHPMailer\Exception('stopped by the test');
        });

        try {
            (new EmailChannel())->send(new Message('customer@example.com', 'Booked', 'Hello <b>Ali</b>'));
            self::fail('The stopped mailer should not have sent anything.');
        } catch (\PHPMailer\PHPMailer\Exception | DeliveryFailed) {
            // Expected: see above.
        }

        self::assertSame(['text/plain'], $this->contentTypes);
        self::assertSame(
            'text/html',
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core's hook, read as wp_mail() does.
            \apply_filters('wp_mail_content_type', 'text/plain'),
            'the site filter is back for the mail of other plugins'
        );
    }
}
