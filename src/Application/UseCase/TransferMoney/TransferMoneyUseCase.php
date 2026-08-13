<?php

declare(strict_types=1);

namespace App\Application\UseCase\TransferMoney;

use App\Application\Port\AuthorizerGatewayInterface;
use App\Application\Port\NotifierInterface;
use App\Application\Port\TransactionManagerInterface;
use App\Domain\Exception\InvalidMoneyAmountException;
use App\Domain\Exception\TransferNotAuthorizedException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\Transaction;
use App\Domain\Model\User;
use App\Domain\Repository\TransactionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\TransferPolicy;
use App\Domain\ValueObject\Money;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates a transfer end to end:
 *
 *  1. Lock payer and payee (always in ascending id order — see {@see lockBothInAscendingIdOrder})
 *     and run the pure business rules in {@see TransferPolicy}.
 *  2. Ask the external authorizer; unavailability is treated as a denial (fail-closed).
 *  3. Move the money and record a COMPLETED transaction — all inside one DB transaction, so any
 *     failure anywhere above reverts the payer's balance exactly as the challenge requires.
 *  4. Only once that transaction has committed, dispatch the (async, best-effort) payee
 *     notification and, on an authorizer denial, record a REJECTED audit row — deliberately
 *     outside step 3's transaction, so a rollback there can never also erase the evidence of why
 *     it happened.
 */
final class TransferMoneyUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly AuthorizerGatewayInterface $authorizer,
        private readonly NotifierInterface $notifier,
        private readonly TransferPolicy $policy,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(TransferMoneyCommand $command): Transaction
    {
        $amount = Money::fromDecimal($command->value);

        if (!$amount->isPositive()) {
            throw InvalidMoneyAmountException::mustBePositive();
        }

        try {
            $transaction = $this->transactionManager->transactional(
                function () use ($command, $amount): Transaction {
                    $users = $this->lockBothInAscendingIdOrder($command->payerId, $command->payeeId);
                    $payer = $users[$command->payerId];
                    $payee = $users[$command->payeeId];

                    $this->policy->assertTransferIsAllowed($payer, $payee, $amount);

                    if (!$this->authorizer->authorize()) {
                        throw TransferNotAuthorizedException::deniedByAuthorizer();
                    }

                    $payer->debit($amount);
                    $payee->credit($amount);

                    $transaction = Transaction::completed($payer, $payee, $amount, 'authorized');
                    $this->transactions->add($transaction);

                    return $transaction;
                },
            );
        } catch (TransferNotAuthorizedException $exception) {
            $this->recordRejection($command, $amount, $exception->errorCode());

            throw $exception;
        }

        $this->notifier->notifyPayeeOfTransfer($transaction);

        return $transaction;
    }

    /**
     * @return array<int, User> the payer and payee, keyed by their own id, both locked
     *
     * @throws UserNotFoundException
     */
    private function lockBothInAscendingIdOrder(int $payerId, int $payeeId): array
    {
        $orderedIds = $payerId <= $payeeId ? [$payerId, $payeeId] : [$payeeId, $payerId];

        $usersById = [];
        foreach (array_unique($orderedIds) as $id) {
            $usersById[$id] = $this->users->getByIdForUpdate($id);
        }

        // Self-transfers (payerId === payeeId) collapse to a single locked row here; the
        // duplicate lookups below simply return that same instance for both roles, and
        // TransferPolicy is what actually rejects the self-transfer.
        return [
            $payerId => $usersById[$payerId],
            $payeeId => $usersById[$payeeId],
        ];
    }

    /**
     * Runs in its own, separate transaction — deliberately outside the (already rolled back)
     * transfer transaction — so an authorizer denial still leaves an auditable trace.
     */
    private function recordRejection(TransferMoneyCommand $command, Money $amount, string $reason): void
    {
        try {
            $this->transactionManager->transactional(function () use ($command, $amount, $reason): void {
                $payer = $this->users->findById($command->payerId);
                $payee = $this->users->findById($command->payeeId);

                if ($payer === null || $payee === null) {
                    return;
                }

                $this->transactions->add(Transaction::rejected($payer, $payee, $amount, $reason));
            });
        } catch (\Throwable $exception) {
            // Auditing the rejection must never mask the original, user-facing denial.
            $this->logger->error('Failed to persist rejected-transfer audit record.', [
                'payer_id' => $command->payerId,
                'payee_id' => $command->payeeId,
                'exception' => $exception,
            ]);
        }
    }
}
