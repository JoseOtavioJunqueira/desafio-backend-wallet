<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InsufficientFundsException extends AbstractDomainException
{
    public static function forUser(int $userId): self
    {
        return new self(
            "User #{$userId} does not have enough balance for this transfer.",
            'insufficient_funds',
            422,
        );
    }
}
