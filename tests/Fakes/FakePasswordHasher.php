<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Application\Port\PasswordHasherInterface;

final class FakePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }
}
