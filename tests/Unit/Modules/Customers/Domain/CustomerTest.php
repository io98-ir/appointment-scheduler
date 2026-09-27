<?php

declare(strict_types=1);

namespace Vaqtyar\Tests\Unit\Modules\Customers\Domain;

use PHPUnit\Framework\TestCase;
use Vaqtyar\Modules\Customers\Domain\Customer;
use Vaqtyar\Modules\Customers\Domain\CustomerStatus;
use Vaqtyar\Shared\Domain\InvalidValue;
use Vaqtyar\Shared\Domain\PhoneNumber;

final class CustomerTest extends TestCase
{
    public function testTheFullNameJoinsWhatIsGiven(): void
    {
        self::assertSame('علی کریمی', self::customer('علی', 'کریمی')->fullName());
        self::assertSame('کریمی', self::customer('', 'کریمی')->fullName());
        self::assertSame('Ali', self::customer('Ali', '')->fullName());
    }

    public function testTagsAreTrimmedAndDeduplicated(): void
    {
        $customer = new Customer(null, null, 'Ali', '', self::phone(), tags: [' VIP ', 'VIP', 'new', '12']);

        self::assertSame(['VIP', 'new', '12'], $customer->tags);
    }

    public function testOnlyAnActiveCustomerCanBook(): void
    {
        self::assertTrue(self::customer('Ali', '')->canBook());
        self::assertFalse(
            (new Customer(null, null, 'Ali', '', self::phone(), status: CustomerStatus::Blocked))->canBook()
        );
    }

    /**
     * @return iterable<string, array{\Closure(): Customer, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no name' => [static fn () => self::customer('', ''), 'invalid_name'];
        yield 'untrimmed name' => [static fn () => self::customer(' Ali', ''), 'invalid_name'];
        yield 'two lines' => [static fn () => self::customer("Ali\nReza", ''), 'invalid_name'];
        yield 'long name' => [static fn () => self::customer(\str_repeat('ع', 101), ''), 'invalid_name'];
        yield 'bad utf-8' => [static fn () => self::customer("\xC3", ''), 'invalid_name'];
        yield 'zero id' => [static fn () => new Customer(0, null, 'Ali', '', self::phone()), 'invalid_id'];
        yield 'zero user' => [
            static fn () => new Customer(null, null, 'Ali', '', self::phone(), wpUserId: 0),
            'invalid_id',
        ];
        yield 'empty tag' => [
            static fn () => new Customer(null, null, 'Ali', '', self::phone(), tags: [' ']),
            'invalid_tag',
        ];
        yield 'long tag' => [
            static fn () => new Customer(null, null, 'Ali', '', self::phone(), tags: [\str_repeat('x', 51)]),
            'invalid_tag',
        ];
        yield 'too many tags' => [
            static fn () => new Customer(null, null, 'Ali', '', self::phone(), tags: \array_map(
                \strval(...),
                \range(1, 21)
            )),
            'too_many_tags',
        ];
        yield 'long note' => [
            static fn () => new Customer(null, null, 'Ali', '', self::phone(), note: \str_repeat('x', 65536)),
            'text_too_long',
        ];
    }

    /**
     * @dataProvider invalid
     * @param \Closure(): Customer $build
     */
    public function testInvalidCustomersAreRefused(\Closure $build, string $code): void
    {
        try {
            $build();
            self::fail('No exception.');
        } catch (InvalidValue $e) {
            self::assertSame($code, $e->errorCode);
        }
    }

    private static function customer(string $first, string $last): Customer
    {
        return new Customer(null, null, $first, $last, self::phone());
    }

    private static function phone(): PhoneNumber
    {
        return PhoneNumber::fromInput('09121234567');
    }
}
