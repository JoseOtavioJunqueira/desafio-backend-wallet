<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Model\Transaction;
use App\Domain\Repository\TransactionRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function add(Transaction $transaction): void
    {
        $this->entityManager->persist($transaction);
    }

    public function findById(int $id): ?Transaction
    {
        return $this->entityManager->find(Transaction::class, $id);
    }
}
