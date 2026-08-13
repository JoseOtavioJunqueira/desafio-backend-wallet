<?php

declare(strict_types=1);

namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class DepositRequest
{
    public function __construct(
        #[Assert\NotBlank(message: '"value" is required.')]
        #[Assert\Positive(message: '"value" must be greater than zero.')]
        public readonly float $value = 0.0,
    ) {
    }
}
