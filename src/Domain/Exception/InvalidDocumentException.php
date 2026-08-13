<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidDocumentException extends AbstractDomainException
{
    public static function forValue(string $rawValue): self
    {
        return new self(
            "\"{$rawValue}\" is not a valid CPF or CNPJ.",
            'invalid_document',
            422,
        );
    }
}
