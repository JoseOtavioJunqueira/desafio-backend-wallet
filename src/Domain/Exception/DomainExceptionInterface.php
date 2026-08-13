<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Marker implemented by every business-rule violation raised from the Domain layer.
 *
 * Carrying the HTTP status and a machine-readable code here (instead of in the Http layer)
 * keeps the mapping next to the rule it describes and lets the exception listener stay a
 * dumb translator instead of a second copy of the business rules.
 */
interface DomainExceptionInterface extends \Throwable
{
    public function errorCode(): string;

    public function httpStatusCode(): int;
}
