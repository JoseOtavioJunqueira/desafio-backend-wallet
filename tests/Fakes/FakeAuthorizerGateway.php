<?php

declare(strict_types=1);

namespace App\Tests\Fakes;

use App\Application\Port\AuthorizerGatewayInterface;

final class FakeAuthorizerGateway implements AuthorizerGatewayInterface
{
    private int $callCount = 0;

    public function __construct(private bool $authorized = true)
    {
    }

    public function authorize(): bool
    {
        $this->callCount++;

        return $this->authorized;
    }

    public function deny(): void
    {
        $this->authorized = false;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
