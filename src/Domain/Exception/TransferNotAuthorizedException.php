<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Raised when the external authorizer service denies the transfer, or when it cannot be
 * reached at all. Unavailability is treated as a denial (fail-closed): PicPay Simplificado
 * never moves money without an explicit "authorized" answer.
 */
final class TransferNotAuthorizedException extends AbstractDomainException
{
    public static function deniedByAuthorizer(): self
    {
        return new self('The external authorizer denied this transfer.', 'transfer_not_authorized', 403);
    }

    public static function authorizerUnavailable(): self
    {
        return new self(
            'The external authorizer is unavailable; the transfer was denied as a safety default.',
            'transfer_not_authorized',
            403,
        );
    }
}
