<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Application\Port\NotifierInterface;
use App\Domain\Model\Transaction;

final class FakeNotifier implements NotifierInterface
{
    /** @var list<Transaction> */
    private array $notified = [];

    public function notifyPayeeOfTransfer(Transaction $transaction): void
    {
        $this->notified[] = $transaction;
    }

    /** @return list<Transaction> */
    public function notified(): array
    {
        return $this->notified;
    }
}
