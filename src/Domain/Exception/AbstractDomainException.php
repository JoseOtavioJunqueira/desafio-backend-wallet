<?php

declare(strict_types=1);

namespace App\Domain\Exception;

abstract class AbstractDomainException extends \RuntimeException implements DomainExceptionInterface
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $httpStatusCode,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
