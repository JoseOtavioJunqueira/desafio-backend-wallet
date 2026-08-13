<?php

declare(strict_types=1);

namespace App\Application\UseCase\DepositMoney;

final class DepositMoneyCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly string|float|int $value,
    ) {
    }
}
