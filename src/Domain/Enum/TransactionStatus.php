<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum TransactionStatus: string
{
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
}
