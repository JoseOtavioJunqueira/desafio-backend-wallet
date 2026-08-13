<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Enum\UserType;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Model\User;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_new_user_starts_with_a_zero_balance(): void
    {
        $user = $this->newUser(UserType::COMMON);

        self::assertTrue($user->balance()->equals(Money::zero()));
    }

    public function test_credit_increases_balance(): void
    {
        $user = $this->newUser(UserType::COMMON);

        $user->credit(Money::fromDecimal('50.00'));

        self::assertSame('50.00', (string) $user->balance());
    }

    public function test_debit_decreases_balance(): void
    {
        $user = $this->newUser(UserType::COMMON);
        $user->credit(Money::fromDecimal('50.00'));

        $user->debit(Money::fromDecimal('20.00'));

        self::assertSame('30.00', (string) $user->balance());
    }

    public function test_debit_more_than_balance_throws_and_leaves_balance_untouched(): void
    {
        $user = $this->newUser(UserType::COMMON);
        $user->credit(Money::fromDecimal('10.00'));

        try {
            $user->debit(Money::fromDecimal('10.01'));
            self::fail('Expected InsufficientFundsException was not thrown.');
        } catch (InsufficientFundsException) {
            // expected
        }

        self::assertSame('10.00', (string) $user->balance());
    }

    public function test_only_common_users_can_send_money(): void
    {
        self::assertTrue($this->newUser(UserType::COMMON)->canSendMoney());
        self::assertFalse($this->newUser(UserType::MERCHANT)->canSendMoney());
    }

    private function newUser(UserType $type): User
    {
        return new User('Test User', Document::cpf('52998224725'), 'user@example.com', 'hash', $type);
    }
}
