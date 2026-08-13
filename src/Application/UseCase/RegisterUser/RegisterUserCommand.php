<?php

declare(strict_types=1);

namespace App\Application\UseCase\RegisterUser;

use App\Domain\Enum\UserType;

final class RegisterUserCommand
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $document,
        public readonly string $email,
        public readonly string $plainPassword,
        public readonly UserType $type,
    ) {
    }
}
