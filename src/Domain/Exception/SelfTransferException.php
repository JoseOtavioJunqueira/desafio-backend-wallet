<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class SelfTransferException extends AbstractDomainException
{
    public static function create(): self
    {
        return new self('A user cannot transfer money to themselves.', 'self_transfer_not_allowed', 422);
    }
}
