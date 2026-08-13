<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function transactional(callable $operation): mixed
    {
        // Begins a transaction, runs $operation, flushes the unit of work and commits — or
        // rolls back the whole thing (including the pessimistic locks taken inside it) if
        // $operation throws. Exactly the "revertida em qualquer caso de inconsistência"
        // guarantee the challenge asks for around the transfer.
        return $this->entityManager->wrapInTransaction($operation);
    }
}
