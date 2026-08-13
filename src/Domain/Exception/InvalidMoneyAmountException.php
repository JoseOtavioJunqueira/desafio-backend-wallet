<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidMoneyAmountException extends AbstractDomainException
{
    public static function mustBePositive(): self
    {
        return new self('Transfer value must be greater than zero.', 'invalid_money_amount', 422);
    }

    public static function resultingBalanceCannotBeNegative(): self
    {
        return new self('This operation would result in a negative balance.', 'invalid_money_amount', 422);
    }
}
