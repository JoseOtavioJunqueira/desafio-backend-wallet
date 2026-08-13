<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Enum\UserType;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Document;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Proves, against real PostgreSQL, the row-locking primitive
 * App\Infrastructure\Persistence\Doctrine\DoctrineUserRepository::getByIdForUpdate() and
 * App\Application\UseCase\TransferMoney\TransferMoneyUseCase both depend on: a second
 * transaction cannot read-lock (`FOR UPDATE`) a row that a first, still-open transaction has
 * already locked — which is exactly what stops two simultaneous transfers from the same payer
 * from both reading a stale balance and driving it negative.
 *
 * This needs two genuinely independent database connections, so it deliberately bypasses both
 * Doctrine's container-managed connection and DAMA\DoctrineTestBundle's transaction-per-test
 * wrapping (#[SkipDatabaseRollback]) — DAMA routes every container-resolved connection through
 * a single shared static connection specifically so nested test transactions can roll back
 * cleanly, which would make two "independent" locks indistinguishable from one.
 */
#[SkipDatabaseRollback]
final class ConcurrentTransferLockTest extends KernelTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $repository = $container->get(UserRepositoryInterface::class);

        $user = new User('Lock Test User', Document::fromRaw('52998224725'), 'lock-test@example.com', 'hash', UserType::COMMON);
        $repository->add($user);
        $entityManager->flush();
        $this->userId = $user->id();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement('DELETE FROM users WHERE id = ?', [$this->userId]);

        parent::tearDown();
    }

    public function test_for_update_blocks_a_concurrent_transaction_on_the_same_row(): void
    {
        $connectionA = $this->independentConnection();
        $connectionB = $this->independentConnection();

        try {
            $connectionA->beginTransaction();
            $connectionA->executeQuery('SELECT id FROM users WHERE id = ? FOR UPDATE', [$this->userId]);

            // Connection A now holds the lock and has not committed. A second transaction
            // trying to lock the same row must not be able to proceed immediately.
            $connectionB->executeStatement("SET statement_timeout = '500'"); // milliseconds
            $connectionB->beginTransaction();

            $blocked = false;

            try {
                $connectionB->executeQuery('SELECT id FROM users WHERE id = ? FOR UPDATE', [$this->userId]);
            } catch (DBALException) {
                $blocked = true;
            }

            self::assertTrue($blocked, 'A second transaction was able to lock a row already locked by an open, uncommitted transaction.');
        } finally {
            // A canceled statement leaves the DBAL connection's transaction in an aborted
            // state; roll it back explicitly before releasing connection A's lock.
            if ($connectionB->isTransactionActive()) {
                $connectionB->rollBack();
            }
            if ($connectionA->isTransactionActive()) {
                $connectionA->commit();
            }
        }

        // Once A releases the lock, B must be able to acquire it — proving the block above was
        // genuine serialization, not a stuck/broken connection.
        $connectionB->beginTransaction();
        $connectionB->executeQuery('SELECT id FROM users WHERE id = ? FOR UPDATE', [$this->userId]);
        $connectionB->commit();

        self::assertTrue(true);
    }

    private function independentConnection(): Connection
    {
        $url = (string) ($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL'));
        $parts = parse_url($url);

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 5432,
            'user' => $parts['user'],
            'password' => $parts['pass'],
            // Mirrors config/packages/doctrine.yaml's `when@test: dbname_suffix: '_test'`.
            'dbname' => ltrim($parts['path'], '/') . '_test',
        ]);
    }
}
