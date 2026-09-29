<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Notifications;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Notifications\Domain\Audience;
use Vaqtyar\Modules\Notifications\Domain\QuietHours;
use Vaqtyar\Modules\Notifications\Domain\Template;
use Vaqtyar\Modules\Notifications\Domain\TemplateRenderer;
use Vaqtyar\Modules\Notifications\Domain\Trigger;
use Vaqtyar\Modules\Notifications\Infrastructure\Persistence\DefaultTemplates;
use Vaqtyar\Shared\Domain\InvalidValue;

final class DomainTest extends TestCase
{
    public function testRendererFillsKnownNamesAndEmptiesUnknownOnes(): void
    {
        self::assertSame(
            'Hi Ali, at .',
            (new TemplateRenderer())->render('Hi {name}, at {nope}.', ['name' => 'Ali'])
        );
    }

    public function testRendererKeepsValuesOnOneLine(): void
    {
        self::assertSame(
            'Hi Ali Bcc: x',
            (new TemplateRenderer())->render('Hi {name}', ['name' => "Ali\r\nBcc: x"])
        );
    }

    public function testRendererLeavesTextWithoutPlaceholdersAlone(): void
    {
        self::assertSame("line one\nline two", (new TemplateRenderer())->render("line one\nline two", []));
    }

    public function testOnlyAReminderHasAnOffset(): void
    {
        $this->expectException(InvalidValue::class);
        new Template(null, Trigger::Booked, Audience::Customer, 'email', 60, '', 'body');
    }

    public function testAReminderNeedsAnOffset(): void
    {
        $this->expectException(InvalidValue::class);
        new Template(null, Trigger::Reminder, Audience::Customer, 'email', null, '', 'body');
    }

    public function testABodyIsRequired(): void
    {
        $this->expectException(InvalidValue::class);
        new Template(null, Trigger::Booked, Audience::Customer, 'email', null, '', "  \n");
    }

    public function testAChannelIsAShortLowercaseWord(): void
    {
        $this->expectException(InvalidValue::class);
        new Template(null, Trigger::Booked, Audience::Customer, 'E-mail', null, '', 'body');
    }

    public function testTheDefaultTemplatesAreValidAndCoverTheCustomer(): void
    {
        $templates = DefaultTemplates::all();
        $customer = \array_filter($templates, static fn (Template $t): bool => Audience::Customer === $t->audience);

        self::assertCount(4, $customer);
        self::assertCount(
            4,
            \array_unique(\array_map(static fn (Template $t): string => $t->trigger->value, $customer))
        );
    }

    /**
     * @dataProvider quietCases
     */
    public function testQuietHours(string $local, ?string $endsAt): void
    {
        $zone = new \DateTimeZone('Asia/Tehran');
        $at = (new \DateTimeImmutable($local, $zone))->getTimestamp();
        $ends = (new QuietHours(22 * 60, 8 * 60))->endsAfter($at, $zone);

        self::assertSame(
            null === $endsAt ? null : (new \DateTimeImmutable($endsAt, $zone))->getTimestamp(),
            $ends
        );
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function quietCases(): array
    {
        return [
            'evening, inside' => ['2026-10-01 23:30', '2026-10-02 08:00'],
            'after midnight, inside' => ['2026-10-02 03:00', '2026-10-02 08:00'],
            'window starts' => ['2026-10-01 22:00', '2026-10-02 08:00'],
            'window ends, outside' => ['2026-10-02 08:00', null],
            'daytime, outside' => ['2026-10-02 14:00', null],
        ];
    }

    public function testEqualEndsMeanNoQuietHours(): void
    {
        self::assertNull((new QuietHours(0, 0))->endsAfter(1_790_000_000, new \DateTimeZone('UTC')));
    }

    public function testAQuietWindowThatDoesNotCrossMidnight(): void
    {
        $zone = new \DateTimeZone('UTC');
        $quiet = new QuietHours(13 * 60, 15 * 60);
        $at = (new \DateTimeImmutable('2026-10-02 13:30', $zone))->getTimestamp();

        $end = (new \DateTimeImmutable('2026-10-02 15:00', $zone))->getTimestamp();

        self::assertSame($end, $quiet->endsAfter($at, $zone));
        self::assertNull($quiet->endsAfter((new \DateTimeImmutable('2026-10-02 16:00', $zone))->getTimestamp(), $zone));
    }
}
