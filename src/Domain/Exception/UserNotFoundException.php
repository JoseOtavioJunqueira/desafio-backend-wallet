<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class UserNotFoundException extends AbstractDomainException
{
    public static function withId(int $id): self
    {
        return new self("User #{$id} was not found.", 'user_not_found', 404);
    }
}
