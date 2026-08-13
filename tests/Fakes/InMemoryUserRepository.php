<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * In-memory double for unit-testing use cases without a database. It does not, and cannot,
 * reproduce PostgreSQL's row locking — that guarantee is exercised for real in
 * tests/Integration/Persistence/ConcurrentTransferTest.php.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $usersById = [];
    private int $nextId = 1;

    public function add(User $user): void
    {
        $id = $this->nextId++;
        $this->assignId($user, $id);
        $this->usersById[$id] = $user;
    }

    public function findById(int $id): ?User
    {
        return $this->usersById[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->usersById as $user) {
            if ($user->email() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findByDocumentNumber(string $documentNumber): ?User
    {
        foreach ($this->usersById as $user) {
            if ($user->document()->number() === $documentNumber) {
                return $user;
            }
        }

        return null;
    }

    public function getByIdForUpdate(int $id): User
    {
        return $this->usersById[$id] ?? throw UserNotFoundException::withId($id);
    }

    private function assignId(User $user, int $id): void
    {
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setValue($user, $id);
    }
}
