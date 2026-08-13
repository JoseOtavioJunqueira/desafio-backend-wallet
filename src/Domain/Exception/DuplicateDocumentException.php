<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class DuplicateDocumentException extends AbstractDomainException
{
    public static function forDocument(string $documentNumber): self
    {
        return new self(
            "A user with document \"{$documentNumber}\" already exists.",
            'duplicate_document',
            409,
        );
    }
}
