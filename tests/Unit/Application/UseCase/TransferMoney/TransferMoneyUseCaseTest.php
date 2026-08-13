<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\TransferMoney;

use App\Application\UseCase\TransferMoney\TransferMoneyCommand;
use App\Application\UseCase\TransferMoney\TransferMoneyUseCase;
use App\Domain\Enum\TransactionStatus;
use App\Domain\Enum\UserType;
use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\InvalidMoneyAmountException;
use App\Domain\Exception\MerchantCannotSendMoneyException;
use App\Domain\Exception\SelfTransferException;
use App\Domain\Exception\TransferNotAuthorizedException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Service\TransferPolicy;
use App\Domain\ValueObject\Document;
use App\Domain\ValueObject\Money;
use App\Tests\Fakes\FakeAuthorizerGateway;
use App\Tests\Fakes\FakeNotifier;
use App\Tests\Fakes\InMemoryTransactionRepository;
use App\Tests\Fakes\InMemoryUserRepository;
use App\Tests\Fakes\PassthroughTransactionManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TransferMoneyUseCaseTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryTransactionRepository $transactions;
    private FakeAuthorizerGateway $authorizer;
    private FakeNotifier $notifier;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->transactions = new InMemoryTransactionRepository();
        $this->authorizer = new FakeAuthorizerGateway(authorized: true);
        $this->notifier = new FakeNotifier();
    }

    public function test_a_funded_common_user_can_pay_a_merchant(): void
    {
        $payer = $this->registerUser(UserType::COMMON, '52998224725', 'payer@example.com');
        $payer->credit(Money::fromDecimal('100.00'));
        $payee = $this->registerUser(UserType::MERCHANT, '11444777000161', 'payee@example.com');

        $transaction = $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 30.0));

        self::assertSame(TransactionStatus::COMPLETED, $transaction->status());
        self::assertSame('70.00', (string) $payer->balance());
        self::assertSame('30.00', (string) $payee->balance());
        self::assertCount(1, $this->notifier->notified());
        self::assertSame($transaction, $this->notifier->notified()[0]);
    }

    public function test_merchant_cannot_be_a_payer(): void
    {
        $payer = $this->registerUser(UserType::MERCHANT, '11444777000161', 'merchant@example.com');
        $payer->credit(Money::fromDecimal('100.00'));
        $payee = $this->registerUser(UserType::COMMON, '52998224725', 'payee@example.com');

        $this->expectException(MerchantCannotSendMoneyException::class);

        $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 10.0));
    }

    public function test_insufficient_balance_is_rejected_and_authorizer_is_never_called(): void
    {
        $payer = $this->registerUser(UserType::COMMON, '52998224725', 'payer@example.com');
        $payer->credit(Money::fromDecimal('5.00'));
        $payee = $this->registerUser(UserType::COMMON, '39053344705', 'payee@example.com');

        try {
            $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 10.0));
            self::fail('Expected InsufficientFundsException.');
        } catch (InsufficientFundsException) {
            // expected
        }

        self::assertSame(0, $this->authorizer->callCount());
        self::assertSame('5.00', (string) $payer->balance());
    }

    public function test_a_user_cannot_transfer_to_themselves(): void
    {
        $user = $this->registerUser(UserType::COMMON, '52998224725', 'user@example.com');
        $user->credit(Money::fromDecimal('50.00'));

        $this->expectException(SelfTransferException::class);

        $this->useCase()->execute(new TransferMoneyCommand($user->id(), $user->id(), 10.0));
    }

    public function test_transfer_value_must_be_positive(): void
    {
        $payer = $this->registerUser(UserType::COMMON, '52998224725', 'payer@example.com');
        $payer->credit(Money::fromDecimal('50.00'));
        $payee = $this->registerUser(UserType::COMMON, '39053344705', 'payee@example.com');

        $this->expectException(InvalidMoneyAmountException::class);

        $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 0.0));
    }

    public function test_unknown_payer_is_reported_as_not_found(): void
    {
        $payee = $this->registerUser(UserType::COMMON, '52998224725', 'payee@example.com');

        $this->expectException(UserNotFoundException::class);

        $this->useCase()->execute(new TransferMoneyCommand(9999, $payee->id(), 10.0));
    }

    public function test_authorizer_denial_reverts_the_balance_and_never_notifies(): void
    {
        $payer = $this->registerUser(UserType::COMMON, '52998224725', 'payer@example.com');
        $payer->credit(Money::fromDecimal('100.00'));
        $payee = $this->registerUser(UserType::COMMON, '39053344705', 'payee@example.com');
        $this->authorizer->deny();

        try {
            $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 30.0));
            self::fail('Expected TransferNotAuthorizedException.');
        } catch (TransferNotAuthorizedException) {
            // expected
        }

        self::assertSame('100.00', (string) $payer->balance());
        self::assertSame('0.00', (string) $payee->balance());
        self::assertCount(0, $this->notifier->notified());
    }

    public function test_authorizer_denial_still_records_an_auditable_rejected_transaction(): void
    {
        $payer = $this->registerUser(UserType::COMMON, '52998224725', 'payer@example.com');
        $payer->credit(Money::fromDecimal('100.00'));
        $payee = $this->registerUser(UserType::COMMON, '39053344705', 'payee@example.com');
        $this->authorizer->deny();

        try {
            $this->useCase()->execute(new TransferMoneyCommand($payer->id(), $payee->id(), 30.0));
        } catch (TransferNotAuthorizedException) {
            // expected — assertions happen on the side-effect below
        }

        $recorded = $this->transactions->all();
        self::assertCount(1, $recorded);
        self::assertSame(TransactionStatus::REJECTED, $recorded[0]->status());
        self::assertSame('30.00', (string) $recorded[0]->amount());
    }

    private function registerUser(UserType $type, string $document, string $email): User
    {
        $user = new User('Test User', Document::fromRaw($document), $email, 'hash', $type);
        $this->users->add($user);

        return $user;
    }

    private function useCase(): TransferMoneyUseCase
    {
        return new TransferMoneyUseCase(
            $this->users,
            $this->transactions,
            new PassthroughTransactionManager(),
            $this->authorizer,
            $this->notifier,
            new TransferPolicy(),
            new NullLogger(),
        );
    }
}
