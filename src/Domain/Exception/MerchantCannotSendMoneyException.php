<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class MerchantCannotSendMoneyException extends AbstractDomainException
{
    public static function forUser(int $userId): self
    {
        return new self(
            "User #{$userId} is a merchant and cannot send money.",
            'merchant_cannot_send_money',
            403,
        );
    }
}
