<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;

interface UserRepositoryInterface
{
    public function add(User $user): void;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByDocumentNumber(string $documentNumber): ?User;

    /**
     * Loads a user with a pessimistic write lock, for use inside an active transaction
     * (see {@see \App\Application\Port\TransactionManagerInterface}). Callers must always lock
     * multiple users in ascending id order to avoid deadlocks between concurrent transfers.
     *
     * @throws UserNotFoundException
     */
    public function getByIdForUpdate(int $id): User;
}
