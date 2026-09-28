<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\PolicyAdminService;
use Vaqtyar\Modules\Booking\Domain\Policy\CancellationPolicy;
use Vaqtyar\Modules\Booking\Domain\Policy\PolicyRepository;
use Vaqtyar\Modules\Booking\Domain\Policy\RefundTier;
use Vaqtyar\Modules\Booking\Domain\Policy\ReschedulePolicy;
use Vaqtyar\Modules\Catalog\Contracts\CatalogApi;
use Vaqtyar\Shared\Domain\Authorizer;
use Vaqtyar\Shared\Domain\Forbidden;
use Vaqtyar\Shared\Domain\NotFound;

final class PolicyAdminServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CatalogApi&MockInterface $catalog;

    private PolicyRepository&MockInterface $policies;

    public bool $allowed = true;

    private PolicyAdminService $service;

    protected function setUp(): void
    {
        /** @var CatalogApi&MockInterface $catalog Mockery has no PHPStan extension here. */
        $catalog = Mockery::mock(CatalogApi::class);
        $this->catalog = $catalog;
        $this->catalog->allows('isStored')->andReturnUsing(
            static fn (string $kind, int $id): bool => 'service' === $kind && 5 === $id
        );
        /** @var PolicyRepository&MockInterface $policies */
        $policies = Mockery::mock(PolicyRepository::class);
        $this->policies = $policies;
        $authorizer = new class ($this) implements Authorizer {
            public function __construct(private readonly PolicyAdminServiceTest $test)
            {
            }

            public function allows(string $capability): bool
            {
                return PolicyAdminService::CAPABILITY === $capability && $this->test->allowed;
            }
        };
        $this->service = new PolicyAdminService($authorizer, $this->catalog, $this->policies);
    }

    public function testAUserWithoutTheCapabilityIsForbidden(): void
    {
        $this->allowed = false;

        $this->expectException(Forbidden::class);
        $this->service->cancellation(0);
    }

    public function testAnUnknownServiceIsNotFound(): void
    {
        try {
            $this->service->cancellation(999);
            self::fail('No exception.');
        } catch (NotFound $e) {
            self::assertSame('service_not_found', $e->errorCode);
        }
    }

    public function testTheGlobalPolicyNeedsNoService(): void
    {
        $this->policies->expects('findCancellation')->with(0)->andReturnNull();

        self::assertNull($this->service->cancellation(0));
    }

    public function testACancellationPolicyIsSavedReadAndDeletedForAService(): void
    {
        $policy = new CancellationPolicy(24, [new RefundTier(24, 50)]);
        $this->policies->expects('saveCancellation')->with(5, $policy);
        $this->policies->expects('findCancellation')->with(5)->andReturn($policy);
        $this->policies->expects('deleteCancellation')->with(5);

        self::assertSame($policy, $this->service->saveCancellation(5, $policy));
        self::assertSame($policy, $this->service->cancellation(5));
        $this->service->deleteCancellation(5);
    }

    public function testAReschedulePolicyIsSavedReadAndDeletedForAService(): void
    {
        $policy = new ReschedulePolicy(12, 2);
        $this->policies->expects('saveReschedule')->with(5, $policy);
        $this->policies->expects('findReschedule')->with(5)->andReturn($policy);
        $this->policies->expects('deleteReschedule')->with(5);

        self::assertSame($policy, $this->service->saveReschedule(5, $policy));
        self::assertSame($policy, $this->service->reschedule(5));
        $this->service->deleteReschedule(5);
    }
}
