<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class DuplicateEmailException extends AbstractDomainException
{
    public static function forEmail(string $email): self
    {
        return new self("A user with e-mail \"{$email}\" already exists.", 'duplicate_email', 409);
    }
}
