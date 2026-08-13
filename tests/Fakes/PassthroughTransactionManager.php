<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Application\Port\TransactionManagerInterface;

/**
 * Simply runs the operation — no real transaction/rollback. Safe for unit tests only because
 * every use case under test performs its domain mutations (debit/credit) strictly after every
 * check that could fail, so nothing needs to be "rolled back" in memory. Real atomicity is
 * covered by tests/Integration.
 */
final class PassthroughTransactionManager implements TransactionManagerInterface
{
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
