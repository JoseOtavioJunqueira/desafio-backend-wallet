<?php

declare(strict_types=1);

namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Maps 1:1 to the fixed contract the challenge specifies for `POST /transfer`:
 * {"value": 100.0, "payer": 4, "payee": 15}.
 */
final class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: '"value" is required.')]
        #[Assert\Positive(message: '"value" must be greater than zero.')]
        public readonly float $value = 0.0,
        #[Assert\NotBlank(message: '"payer" is required.')]
        #[Assert\Positive(message: '"payer" must be a valid user id.')]
        public readonly int $payer = 0,
        #[Assert\NotBlank(message: '"payee" is required.')]
        #[Assert\Positive(message: '"payee" must be a valid user id.')]
        public readonly int $payee = 0,
    ) {
    }
}
