<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * The two participant kinds of PicPay Simplificado. Only COMMON users may
 * originate a transfer; MERCHANT users can only receive money.
 */
enum UserType: string
{
    case COMMON = 'common';
    case MERCHANT = 'merchant';
}
