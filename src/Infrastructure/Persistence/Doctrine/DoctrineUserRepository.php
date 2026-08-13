<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function add(User $user): void
    {
        $this->entityManager->persist($user);
    }

    public function findById(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findByDocumentNumber(string $documentNumber): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['document.number' => $documentNumber]);
    }

    public function getByIdForUpdate(int $id): User
    {
        // Requires an open transaction; PostgreSQL emits `SELECT ... FOR UPDATE`.
        $user = $this->entityManager->find(User::class, $id, LockMode::PESSIMISTIC_WRITE);

        if ($user === null) {
            throw UserNotFoundException::withId($id);
        }

        return $user;
    }
}
