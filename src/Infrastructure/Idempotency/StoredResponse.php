<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

final class StoredResponse
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $body,
    ) {
    }
}
