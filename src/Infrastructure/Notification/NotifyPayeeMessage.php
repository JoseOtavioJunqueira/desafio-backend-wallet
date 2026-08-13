<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

/**
 * Messenger envelope for an async notification. Carries only scalar ids/amounts — never a
 * Doctrine entity — so the message serializes cleanly onto the transport and stays correct even
 * if consumed by a worker long after this request's entity manager is gone.
 */
final class NotifyPayeeMessage
{
    public function __construct(
        public readonly int $payeeId,
        public readonly int $transactionId,
        public readonly int $amountCents,
    ) {
    }
}
