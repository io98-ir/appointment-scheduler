<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Admin;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Admin\Application\Display;
use Vaqtyar\Modules\Admin\Application\SetupService;
use Vaqtyar\Modules\Admin\Application\SetupStore;
use Vaqtyar\Shared\Domain\Calendar;
use Vaqtyar\Shared\Domain\Digits;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\Language;

final class SetupServiceTest extends TestCase
{
    public function testSavesHowDatesAndLanguageAreShown(): void
    {
        $store = new MemorySetupStore();
        $service = $this->service($store, true);

        self::assertSame(Calendar::Jalali, $service->display()->calendar);
        $service->saveDisplay(new Display(Calendar::Gregorian, Digits::Latin, Language::English));

        self::assertSame(Calendar::Gregorian, $store->display->calendar);
        self::assertSame(Language::English, $service->display()->language);
    }

    public function testSavesAValidBrand(): void
    {
        $store = new MemorySetupStore();

        $saved = $this->service($store, true)->saveBrand('Salon', '', '#112233');

        self::assertSame($saved, $store->brand);
        self::assertSame('#112233', $store->brand->color);
    }

    public function testAnInvalidBrandIsNotStored(): void
    {
        $store = new MemorySetupStore();

        try {
            $this->service($store, true)->saveBrand('Salon', '', 'red');
            self::fail('Expected InvalidValue.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_brand_color', $e->errorCode);
        }
        self::assertSame('', $store->brand->name);
    }

    public function testFinishingTheWizardIsRememberedAndReversible(): void
    {
        $store = new MemorySetupStore();
        $service = $this->service($store, true);

        self::assertFalse($service->onboarded());
        $service->setOnboarded(true);
        self::assertTrue($service->onboarded());
        $service->setOnboarded(false);
        self::assertFalse($service->onboarded());
    }

    public function testEveryCallChecksTheCapability(): void
    {
        $store = new MemorySetupStore();
        $service = $this->service($store, false);

        $calls = [
            static fn () => $service->brand(),
            static fn () => $service->saveBrand('X', '', ''),
            static fn () => $service->onboarded(),
            static fn () => $service->setOnboarded(true),
            static fn () => $service->display(),
            static fn () => $service->saveDisplay(new Display(Calendar::Gregorian, Digits::Latin, Language::English)),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Expected Forbidden.');
            } catch (Forbidden) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame('', $store->brand->name);
        self::assertFalse($store->onboarded);
        self::assertSame(Calendar::Jalali, $store->display->calendar);
    }

    private function service(SetupStore $store, bool $allowed): SetupService
    {
        return new SetupService(new class ($allowed) implements Authorizer {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function allows(string $capability): bool
            {
                return $this->allowed && SetupService::CAPABILITY === $capability;
            }
        }, $store);
    }
}
