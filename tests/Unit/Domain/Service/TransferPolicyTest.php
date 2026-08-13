<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Enum\UserType;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\MerchantCannotSendMoneyException;
use App\Domain\Exception\SelfTransferException;
use App\Domain\Model\User;
use App\Domain\Service\TransferPolicy;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class TransferPolicyTest extends TestCase
{
    private TransferPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new TransferPolicy();
    }

    public function test_allows_a_funded_common_user_to_pay_another_user(): void
    {
        $payer = $this->userWithBalance(UserType::COMMON, Money::fromDecimal('100.00'));
        $payee = $this->userWithBalance(UserType::MERCHANT, Money::zero());

        $this->policy->assertTransferIsAllowed($payer, $payee, Money::fromDecimal('50.00'));

        $this->addToAssertionCount(1); // no exception === allowed
    }

    public function test_merchant_cannot_send_money(): void
    {
        $payer = $this->userWithBalance(UserType::MERCHANT, Money::fromDecimal('100.00'));
        $payee = $this->userWithBalance(UserType::COMMON, Money::zero());

        $this->expectException(MerchantCannotSendMoneyException::class);

        $this->policy->assertTransferIsAllowed($payer, $payee, Money::fromDecimal('10.00'));
    }

    public function test_payer_needs_sufficient_balance(): void
    {
        $payer = $this->userWithBalance(UserType::COMMON, Money::fromDecimal('10.00'));
        $payee = $this->userWithBalance(UserType::COMMON, Money::zero());

        $this->expectException(InsufficientFundsException::class);

        $this->policy->assertTransferIsAllowed($payer, $payee, Money::fromDecimal('10.01'));
    }

    public function test_exact_balance_is_allowed(): void
    {
        $payer = $this->userWithBalance(UserType::COMMON, Money::fromDecimal('10.00'));
        $payee = $this->userWithBalance(UserType::COMMON, Money::zero());

        $this->policy->assertTransferIsAllowed($payer, $payee, Money::fromDecimal('10.00'));

        $this->addToAssertionCount(1);
    }

    public function test_a_user_cannot_pay_themselves(): void
    {
        $user = $this->userWithBalance(UserType::COMMON, Money::fromDecimal('100.00'));
        self::assignId($user, 1);

        $this->expectException(SelfTransferException::class);

        $this->policy->assertTransferIsAllowed($user, $user, Money::fromDecimal('1.00'));
    }

    private function userWithBalance(UserType $type, Money $balance): User
    {
        $user = new User('Test User', Document::cpf('52998224725'), 'user@example.com', 'hash', $type);

        if ($balance->isPositive()) {
            $user->credit($balance);
        }

        return $user;
    }

    /** Entities generate their id only once persisted; tests need one to exercise self-transfer. */
    private static function assignId(User $user, int $id): void
    {
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);
    }
}
