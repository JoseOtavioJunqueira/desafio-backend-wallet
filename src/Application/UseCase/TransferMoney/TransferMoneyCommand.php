<?php

declare(strict_types=1);

namespace App\Application\UseCase\TransferMoney;

final class TransferMoneyCommand
{
    public function __construct(
        public readonly int $payerId,
        public readonly int $payeeId,
        public readonly string|float|int $value,
    ) {
    }
}
