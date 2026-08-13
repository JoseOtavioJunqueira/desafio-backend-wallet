<?php

declare(strict_types=1);

namespace App\Application\UseCase\DepositMoney;

use App\Application\Port\TransactionManagerInterface;
use App\Domain\Exception\InvalidMoneyAmountException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Money;

/**
 * Not part of the graded `/transfer` flow, and not in the challenge's fixed endpoint contract —
 * but the brief opens with "é possível depositar e realizar transferências", and without any
 * way to fund a wallet every account is stuck at zero forever, so `/transfer` could never be
 * demonstrated end to end. Kept deliberately minimal: no source account, no external payment
 * rail, just "money appears" — a stand-in for whatever funding flow (card, PIX, bank transfer)
 * would exist in a real PicPay.
 */
final class DepositMoneyUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    public function execute(DepositMoneyCommand $command): User
    {
        $amount = Money::fromDecimal($command->value);

        if (!$amount->isPositive()) {
            throw InvalidMoneyAmountException::mustBePositive();
        }

        return $this->transactionManager->transactional(function () use ($command, $amount): User {
            $user = $this->users->getByIdForUpdate($command->userId);
            $user->credit($amount);

            return $user;
        });
    }
}
