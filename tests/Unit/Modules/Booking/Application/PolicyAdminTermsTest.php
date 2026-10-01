<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\PolicyAdminService;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyRepository;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\NotFound;

/**
 * The deposit, approval and booking-window policies through PolicyAdminService.
 */
final class PolicyAdminTermsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PolicyRepository&MockInterface $policies;

    public bool $allowed = true;

    private PolicyAdminService $service;

    protected function setUp(): void
    {
        /** @var CatalogApi&MockInterface $catalog Mockery has no PHPStan extension here. */
        $catalog = Mockery::mock(CatalogApi::class);
        $catalog->allows('isStored')->andReturnUsing(
            static fn (string $kind, int $id): bool => 'service' === $kind && 5 === $id
        );
        /** @var PolicyRepository&MockInterface $policies */
        $policies = Mockery::mock(PolicyRepository::class);
        $this->policies = $policies;
        $authorizer = new class ($this) implements Authorizer {
            public function __construct(private readonly PolicyAdminTermsTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return PolicyAdminService::CAPABILITY === $capability && $this->test->allowed;
            }
        };
        $this->service = new PolicyAdminService($authorizer, $catalog, $this->policies);
    }

    public function testTermsAreParsedBeforeTheyAreStoredAndReadBackAsStored(): void
    {
        $stored = ['kind' => 'percent', 'value' => 30, 'required' => true];
        $this->policies->expects('saveConfig')->with('deposit', 5, $stored);
        $this->policies->expects('findConfig')->with('deposit', 5)->andReturn($stored);
        $this->policies->expects('deleteConfig')->with('deposit', 5);

        self::assertSame($stored, $this->service->saveTerms('deposit', 5, $stored + ['junk' => 'x']));
        self::assertSame($stored, $this->service->terms('deposit', 5));
        $this->service->deleteTerms('deposit', 5);
    }

    public function testAPolicyThatMakesNoSenseIsRefusedAndNothingIsStored(): void
    {
        $this->policies->shouldNotReceive('saveConfig');

        try {
            $this->service->saveTerms('deposit', 0, ['kind' => 'percent', 'value' => 400]);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('invalid_policy', $e->errorCode);
        }
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        try {
            $this->service->terms('gift', 0);
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame('unknown_policy_type', $e->errorCode);
        }
    }

    public function testAStoredRowThatNoLongerParsesReadsAsNotSet(): void
    {
        $this->policies->expects('findConfig')->with('approval', 0)->andReturn(['required' => 'maybe']);

        self::assertNull($this->service->terms('approval', 0));
    }

    public function testTheBookingWindowKeepsWhatWasLeftOut(): void
    {
        $this->policies->expects('saveConfig')
            ->with('booking_window', 5, ['min_notice_min' => 30, 'max_advance_days' => null]);

        self::assertSame(
            ['min_notice_min' => 30, 'max_advance_days' => null],
            $this->service->saveTerms('booking_window', 5, ['min_notice_min' => 30])
        );
    }

    public function testNeedsTheCapability(): void
    {
        $this->allowed = false;

        $this->expectException(Forbidden::class);
        $this->service->saveTerms('approval', 0, ['required' => true]);
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        $this->expectException(NotFound::class);
        $this->service->terms('approval', 99);
    }
}
