<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Boundary the Application layer uses to run a use case atomically, without knowing it is
 * backed by Doctrine's EntityManager. Implemented by
 * App\Infrastructure\Persistence\Doctrine\DoctrineTransactionManager.
 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
