<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Domain\Model\Transaction;
use App\Domain\Repository\TransactionRepositoryInterface;

final class InMemoryTransactionRepository implements TransactionRepositoryInterface
{
    /** @var array<int, Transaction> */
    private array $transactionsById = [];
    private int $nextId = 1;

    public function add(Transaction $transaction): void
    {
        $id = $this->nextId++;
        new \ReflectionProperty(Transaction::class, 'id')->setValue($transaction, $id);
        $this->transactionsById[$id] = $transaction;
    }

    public function findById(int $id): ?Transaction
    {
        return $this->transactionsById[$id] ?? null;
    }

    /** @return list<Transaction> */
    public function all(): array
    {
        return array_values($this->transactionsById);
    }
}
