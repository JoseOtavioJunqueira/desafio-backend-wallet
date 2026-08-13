<?php

declare(strict_types=1);

namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: '"fullName" is required.')]
        #[Assert\Length(min: 3, max: 180)]
        public readonly string $fullName = '',
        #[Assert\NotBlank(message: '"document" is required.')]
        public readonly string $document = '',
        #[Assert\NotBlank(message: '"email" is required.')]
        #[Assert\Email(message: '"email" is not a valid e-mail address.')]
        public readonly string $email = '',
        #[Assert\NotBlank(message: '"password" is required.')]
        #[Assert\Length(min: 8, minMessage: '"password" must be at least {{ limit }} characters long.')]
        public readonly string $password = '',
        #[Assert\NotBlank(message: '"type" is required.')]
        #[Assert\Choice(choices: ['common', 'merchant'], message: '"type" must be either "common" or "merchant".')]
        public readonly string $type = '',
    ) {
    }
}
