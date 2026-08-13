<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Application\Port\NotifierInterface;
use App\Domain\Model\Transaction;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class AsyncNotifier implements NotifierInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * By the time this runs, the transfer has already committed — enqueueing the notification
     * is a courtesy, never part of the outcome the caller sees. If the transport itself is down
     * (not the mock notify service, the actual message bus), that must degrade to a logged
     * miss, never a 500 on a transfer that already succeeded.
     */
    public function notifyPayeeOfTransfer(Transaction $transaction): void
    {
        try {
            $this->bus->dispatch(new NotifyPayeeMessage(
                payeeId: $transaction->payee()->id() ?? throw new \LogicException('Payee must be persisted before notifying.'),
                transactionId: $transaction->id() ?? throw new \LogicException('Transaction must be persisted before notifying.'),
                amountCents: $transaction->amount()->cents(),
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to enqueue payee notification; the transfer itself is unaffected.', [
                'transaction_id' => $transaction->id(),
                'exception' => $exception,
            ]);
        }
    }
}
