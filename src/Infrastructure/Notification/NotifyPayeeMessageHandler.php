<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Consumes {@see NotifyPayeeMessage} on the `async` transport (run via
 * `bin/console messenger:consume async`) and calls the mock notification service
 * (`POST https://util.devi.tools/api/v1/notify`, confirmed to reply 204 with no body).
 *
 * Deliberately rethrows on failure: the transport's retry_strategy (config/packages/
 * messenger.yaml) then retries with backoff and finally routes to the `failed` transport instead
 * of silently dropping the notification — while the transfer itself, already committed by the
 * time this runs, is completely unaffected either way.
 */
#[AsMessageHandler]
final class NotifyPayeeMessageHandler
{
    public function __construct(
        #[Autowire(service: 'app.http_client.notification')]
        private readonly HttpClientInterface $httpClient,
        private readonly UserRepositoryInterface $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyPayeeMessage $message): void
    {
        $payee = $this->users->findById($message->payeeId);

        if ($payee === null) {
            $this->logger->warning('Skipping notification: payee no longer exists.', [
                'payee_id' => $message->payeeId,
            ]);

            return;
        }

        try {
            $this->httpClient->request('POST', '', [
                'json' => [
                    'email' => $payee->email(),
                    'transactionId' => $message->transactionId,
                    'amount' => $message->amountCents / 100,
                ],
            ])->getStatusCode();
        } catch (\Throwable $exception) {
            $this->logger->error('Notification service call failed.', [
                'payee_id' => $message->payeeId,
                'transaction_id' => $message->transactionId,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
