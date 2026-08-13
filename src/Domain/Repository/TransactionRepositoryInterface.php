<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Transaction;

interface TransactionRepositoryInterface
{
    public function add(Transaction $transaction): void;

    public function findById(int $id): ?Transaction;
}
