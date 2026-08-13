<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Exception\InsufficientFundsException;
use App\Domain\Exception\MerchantCannotSendMoneyException;
use App\Domain\Exception\SelfTransferException;
use App\Domain\Model\User;
use App\Domain\ValueObject\Money;

/**
 * Pure business rules a transfer must satisfy before any money moves or any external service is
 * called. Deliberately has no dependency on repositories, HTTP, or Doctrine — every rule here is
 * unit-testable with plain objects.
 */
final class TransferPolicy
{
    /**
     * @throws SelfTransferException
     * @throws MerchantCannotSendMoneyException
     * @throws InsufficientFundsException
     */
    public function assertTransferIsAllowed(User $payer, User $payee, Money $amount): void
    {
        if ($payer->id() !== null && $payer->id() === $payee->id()) {
            throw SelfTransferException::create();
        }

        if (!$payer->canSendMoney()) {
            throw MerchantCannotSendMoneyException::forUser($payer->id() ?? 0);
        }

        if (!$payer->balance()->isGreaterThanOrEqualTo($amount)) {
            throw InsufficientFundsException::forUser($payer->id() ?? 0);
        }
    }
}
