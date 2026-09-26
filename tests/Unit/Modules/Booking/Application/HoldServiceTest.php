<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Booking\Application;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Booking\Application\HoldRepository;
use Vaqtyar\Modules\Booking\Application\HoldService;
use Vaqtyar\Modules\Booking\Application\ResourceLocker;
use Vaqtyar\Modules\Booking\Application\StoredHold;
use Vaqtyar\Modules\Booking\Domain\Hold;
use Vaqtyar\Modules\Booking\Domain\HoldToken;
use Vaqtyar\Modules\Scheduling\Contracts\AvailabilityQuery;
use Vaqtyar\Modules\Scheduling\Contracts\Claim;
use Vaqtyar\Modules\Scheduling\Contracts\ClaimScope;
use Vaqtyar\Modules\Scheduling\Contracts\SlotClaims;
use Vaqtyar\Shared\Domain\Conflict;
use Vaqtyar\Shared\Domain\NotFound;
use Vaqtyar\Shared\Domain\TransactionRunner;
use Vaqtyar\Tests\Fixtures\FixedClock;

/**
 * The order that prevents double booking (ADR-004), on fakes that log each
 * step: scope outside the transaction; inside it, locks before the claim,
 * the claim before the write; the cache told after the commit.
 */
final class HoldServiceTest extends TestCase
{
    private const START = 1_800_003_600;

    /** @var list<string> */
    private array $log = [];

    private ?Claim $claim;

    private ClaimScope $scope;

    /** @var array<string, array{StoredHold, ?Hold}> By token hash. */
    private array $stored = [];

    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock('@1800000000');
        $this->scope = new ClaimScope([4, 3], [20, 21], self::START - 600, self::START + 7200);
        $this->claim = new Claim(3, self::START, self::START + 3600, self::START - 600, self::START + 4200, [21]);
    }

    public function testPlaceLocksTheWholeScopeThenClaimsThenWrites(): void
    {
        $placed = $this->service()->place(self::query(), self::START);

        self::assertSame(
            [
                'scope',
                'begin',
                'lock res:20,res:21,staff:3,staff:4 ' . (self::START - 600) . '-' . (self::START + 7200),
                'claim',
                'add',
                'commit',
                'changed',
            ],
            $this->log
        );
        $hold = $placed->hold;
        self::assertSame(
            [1, 5, 3, self::START, self::START + 3600, self::START - 600, self::START + 4200, 2, [40], [21]],
            [
                $hold->locationId,
                $hold->variantId,
                $hold->staffId,
                $hold->start,
                $hold->end,
                $hold->from,
                $hold->to,
                $hold->partySize,
                $hold->extraIds,
                $hold->resourceIds,
            ]
        );
        self::assertSame([1_800_000_000, 1_800_000_600], [$hold->createdAt, $hold->expiresAt]);
        self::assertArrayHasKey(HoldToken::fromString($placed->token)->hash(), $this->stored);
    }

    public function testATakenSlotIsAConflictAndWritesNothing(): void
    {
        $this->claim = null;

        $this->assertConflict();
        self::assertSame(['scope', 'begin', 'lock', 'claim', 'rollback'], $this->steps());
    }

    /**
     * The catalog changed between scope and claim: a unit that was not
     * locked must not be written.
     */
    public function testAClaimOutsideTheLockedScopeIsAConflict(): void
    {
        $this->claim = new Claim(3, self::START, self::START + 3600, self::START - 600, self::START + 4200, [22]);
        $this->assertConflict();

        $this->claim = new Claim(3, self::START, self::START + 3600, self::START - 600, self::START + 7201, [21]);
        $this->assertConflict();
    }

    public function testExtendLocksThenRereadsAndMovesTheExpiry(): void
    {
        $placed = $this->service()->place(self::query(), self::START);
        $this->log = [];
        $this->clock->advance(300);

        $expiresAt = $this->service()->extend(HoldToken::fromString($placed->token));

        self::assertSame(1_800_000_900, $expiresAt);
        self::assertSame(
            [
                'find',
                'begin',
                'lock res:21,staff:3 ' . (self::START - 600) . '-' . (self::START + 4200),
                'find for update',
                'extend 1800000900',
                'commit',
                'changed',
            ],
            $this->log
        );
    }

    public function testAnExpiredOrUnknownHoldCannotBeExtended(): void
    {
        $placed = $this->service()->place(self::query(), self::START);
        $this->clock->advance(600);

        foreach ([$placed->token, HoldToken::generate()->value] as $token) {
            try {
                $this->service()->extend(HoldToken::fromString($token));
                self::fail('No exception.');
            } catch (NotFound $e) {
                self::assertSame('hold_not_found', $e->errorCode);
            }
        }
    }

    public function testAnExtensionStopsAtTheLifetime(): void
    {
        $placed = $this->service()->place(self::query(), self::START);
        $token = HoldToken::fromString($placed->token);
        $this->clock->advance(500);
        $this->service()->extend($token);
        $this->clock->advance(500);

        self::assertSame(1_800_001_200, $this->service()->extend($token));
    }

    public function testPurgeTellsTheCacheOnlyWhenItDeletedSomething(): void
    {
        self::assertSame(0, $this->service()->purgeExpired());
        self::assertNotContains('changed', $this->log);
    }

    private function assertConflict(): void
    {
        try {
            $this->service()->place(self::query(), self::START);
            self::fail('No exception.');
        } catch (Conflict $e) {
            self::assertSame('slot_taken', $e->errorCode);
        }
        self::assertSame([], $this->stored);
        self::assertNotContains('changed', $this->log);
    }

    /**
     * @return list<string> The log without the arguments.
     */
    private function steps(): array
    {
        return \array_map(static fn (string $entry): string => \explode(' ', $entry)[0], $this->log);
    }

    private static function query(): AvailabilityQuery
    {
        return new AvailabilityQuery(5, 1, null, [40], 2);
    }

    private function service(): HoldService
    {
        $test = $this;
        $slots = new class ($test) implements SlotClaims {
            public function __construct(private readonly HoldServiceTest $test)
            {
            }

            public function scope(AvailabilityQuery $query, int $start): ClaimScope
            {
                return $this->test->scopeCalled();
            }

            public function claim(AvailabilityQuery $query, int $start): ?Claim
            {
                return $this->test->claimCalled();
            }
        };
        $locker = new class ($test) implements ResourceLocker {
            public function __construct(private readonly HoldServiceTest $test)
            {
            }

            /**
             * @param list<string> $keys
             */
            public function lock(array $keys, int $from, int $to): void
            {
                $this->test->record('lock ' . \implode(',', $keys) . " {$from}-{$to}");
            }
        };
        $transaction = new class ($test) implements TransactionRunner {
            public function __construct(private readonly HoldServiceTest $test)
            {
            }

            public function run(callable $work): mixed
            {
                $this->test->record('begin');
                try {
                    $result = $work();
                } catch (\Throwable $e) {
                    $this->test->record('rollback');
                    throw $e;
                }
                $this->test->record('commit');

                return $result;
            }
        };

        return new HoldService(
            $slots,
            $locker,
            $this->repository(),
            $transaction,
            $this->clock,
            function (): void {
                $this->record('changed');
            }
        );
    }

    private function repository(): HoldRepository
    {
        $test = $this;

        return new class ($test) implements HoldRepository {
            public function __construct(private readonly HoldServiceTest $test)
            {
            }

            public function add(Hold $hold, string $tokenHash): int
            {
                return $this->test->added($hold, $tokenHash);
            }

            public function find(string $tokenHash, bool $forUpdate = false): ?StoredHold
            {
                return $this->test->found($tokenHash, $forUpdate);
            }

            public function extend(int $id, int $expiresAt): void
            {
                $this->test->extended($expiresAt);
            }

            public function purgeExpired(int $now, int $limit): int
            {
                return 0;
            }
        };
    }

    public function record(string $entry): void
    {
        $this->log[] = $entry;
    }

    public function scopeCalled(): ClaimScope
    {
        $this->record('scope');

        return $this->scope;
    }

    public function claimCalled(): ?Claim
    {
        $this->record('claim');

        return $this->claim;
    }

    public function added(Hold $hold, string $tokenHash): int
    {
        $this->record('add');
        $this->stored[$tokenHash] = [
            new StoredHold(1, $hold->lockKeys(), $hold->from, $hold->to, $hold->createdAt, $hold->expiresAt),
            $hold,
        ];

        return 1;
    }

    public function found(string $tokenHash, bool $forUpdate): ?StoredHold
    {
        $this->record($forUpdate ? 'find for update' : 'find');

        return $this->stored[$tokenHash][0] ?? null;
    }

    public function extended(int $expiresAt): void
    {
        $this->record('extend ' . $expiresAt);
        foreach ($this->stored as $hash => [$stored, $hold]) {
            $this->stored[$hash][0] = new StoredHold(
                $stored->id,
                $stored->lockKeys,
                $stored->from,
                $stored->to,
                $stored->createdAt,
                $expiresAt
            );
        }
    }
}
