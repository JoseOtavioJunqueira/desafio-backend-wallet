<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\Transaction;

/**
 * Notifies the payee that they received money. Implemented as a fire-and-forget dispatch onto
 * Messenger (App\Infrastructure\Notification\AsyncNotifier): the notification provider mock is
 * explicitly documented as unstable, so it must never sit in the critical path of `POST
 * /transfer`, nor be able to roll back money that already moved.
 */
interface NotifierInterface
{
    public function notifyPayeeOfTransfer(Transaction $transaction): void;
}
