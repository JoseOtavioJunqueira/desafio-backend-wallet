<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Enum\UserType;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises App\Infrastructure\Persistence\Doctrine\DoctrineUserRepository against a real
 * PostgreSQL instance (not SQLite/mocks): unique constraints, embedded-field lookups
 * (`document.number`) and pessimistic locking all behave differently enough across database
 * engines that faking this layer would test the fake, not the app. Wrapped in a transaction
 * that's rolled back after every test by DAMA\DoctrineTestBundle (see phpunit.dist.xml).
 */
final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(UserRepositoryInterface::class);
    }

    public function test_persists_and_finds_a_user_by_id(): void
    {
        $user = $this->makeUser('52998224725', 'find-by-id@example.com');

        $this->repository->add($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->findById($user->id());

        self::assertNotNull($found);
        self::assertSame('find-by-id@example.com', $found->email());
    }

    public function test_finds_a_user_by_email(): void
    {
        $user = $this->makeUser('52998224725', 'by-email@example.com');
        $this->repository->add($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNotNull($this->repository->findByEmail('by-email@example.com'));
        self::assertNull($this->repository->findByEmail('nobody@example.com'));
    }

    public function test_finds_a_user_by_embedded_document_number(): void
    {
        $user = $this->makeUser('52998224725', 'by-document@example.com');
        $this->repository->add($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->findByDocumentNumber('52998224725');

        self::assertNotNull($found);
        self::assertSame('by-document@example.com', $found->email());
        self::assertNull($this->repository->findByDocumentNumber('00000000000'));
    }

    public function test_the_email_unique_constraint_is_enforced_at_the_database_level(): void
    {
        $first = $this->makeUser('52998224725', 'duplicate@example.com');
        $this->repository->add($first);
        $this->entityManager->flush();

        $second = $this->makeUser('39053344705', 'duplicate@example.com');
        $this->repository->add($second);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function test_get_by_id_for_update_requires_an_existing_user(): void
    {
        $this->entityManager->wrapInTransaction(function (): void {
            $this->expectException(UserNotFoundException::class);
            $this->repository->getByIdForUpdate(999_999);
        });
    }

    public function test_get_by_id_for_update_returns_the_locked_user(): void
    {
        $user = $this->makeUser('52998224725', 'lock-me@example.com');
        $this->repository->add($user);
        $this->entityManager->flush();
        $id = $user->id();
        $this->entityManager->clear();

        $this->entityManager->wrapInTransaction(function () use ($id): void {
            $locked = $this->repository->getByIdForUpdate($id);
            self::assertSame($id, $locked->id());
        });
    }

    private function makeUser(string $document, string $email): User
    {
        return new User('Test User', Document::fromRaw($document), $email, 'hash', UserType::COMMON);
    }
}
