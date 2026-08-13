<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidMoneyAmountException;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_zero_has_no_cents(): void
    {
        self::assertSame(0, Money::zero()->cents());
        self::assertFalse(Money::zero()->isPositive());
    }

    #[DataProvider('decimalAmounts')]
    public function test_from_decimal_rounds_to_the_nearest_cent(string|float|int $input, int $expectedCents): void
    {
        self::assertSame($expectedCents, Money::fromDecimal($input)->cents());
    }

    /** @return iterable<string, array{0: string|float|int, 1: int}> */
    public static function decimalAmounts(): iterable
    {
        yield 'integer reais' => [100, 10000];
        yield 'plain float' => [100.5, 10050];
        yield 'string with dot' => ['100.50', 10050];
        yield 'string with comma' => ['100,50', 10050];
        yield 'zero' => ['0', 0];
    }

    public function test_add_combines_cents(): void
    {
        $result = Money::fromCents(1000)->add(Money::fromCents(250));

        self::assertSame(1250, $result->cents());
    }

    public function test_subtract_combines_cents(): void
    {
        $result = Money::fromCents(1000)->subtract(Money::fromCents(250));

        self::assertSame(750, $result->cents());
    }

    public function test_subtract_below_zero_throws(): void
    {
        $this->expectException(InvalidMoneyAmountException::class);

        Money::fromCents(100)->subtract(Money::fromCents(101));
    }

    public function test_from_cents_rejects_negative_values(): void
    {
        $this->expectException(InvalidMoneyAmountException::class);

        Money::fromCents(-1);
    }

    public function test_is_greater_than_or_equal_to(): void
    {
        self::assertTrue(Money::fromCents(100)->isGreaterThanOrEqualTo(Money::fromCents(100)));
        self::assertTrue(Money::fromCents(101)->isGreaterThanOrEqualTo(Money::fromCents(100)));
        self::assertFalse(Money::fromCents(99)->isGreaterThanOrEqualTo(Money::fromCents(100)));
    }

    public function test_equals_compares_by_value(): void
    {
        self::assertTrue(Money::fromCents(100)->equals(Money::fromCents(100)));
        self::assertFalse(Money::fromCents(100)->equals(Money::fromCents(101)));
    }

    public function test_string_representation_has_two_decimals(): void
    {
        self::assertSame('100.50', (string) Money::fromCents(10050));
        self::assertSame('0.05', (string) Money::fromCents(5));
    }
}
