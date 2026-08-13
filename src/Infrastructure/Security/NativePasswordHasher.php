<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Port\PasswordHasherInterface;

/**
 * PHP's own password_hash() (bcrypt/argon2i/argon2id depending on build, auto-upgraded by PHP
 * itself over time via PASSWORD_DEFAULT) — no framework Security component needed for a single
 * hash-on-write use case.
 */
final class NativePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }
}
