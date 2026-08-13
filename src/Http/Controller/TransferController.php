<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\UseCase\TransferMoney\TransferMoneyCommand;
use App\Application\UseCase\TransferMoney\TransferMoneyUseCase;
use App\Domain\Exception\TransferNotAuthorizedException;
use App\Domain\Model\Transaction;
use App\Http\Request\TransferRequest;
use App\Infrastructure\Idempotency\IdempotencyGuard;
use App\Infrastructure\Metrics\MetricsRecorder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class TransferController
{
    public function __construct(
        private readonly TransferMoneyUseCase $useCase,
        private readonly IdempotencyGuard $idempotency,
        private readonly MetricsRecorder $metrics,
        #[Autowire(service: 'limiter.transfer')]
        private readonly RateLimiterFactory $transferLimiter,
    ) {
    }

    /**
     * The one endpoint the challenge actually grades. Contract:
     * `POST /transfer {"value": 100.0, "payer": 4, "payee": 15}`.
     */
    #[Route('/transfer', name: 'transfer_create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] TransferRequest $payload, Request $request): JsonResponse
    {
        $limit = $this->transferLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['type' => 'about:blank', 'title' => 'Too Many Requests', 'status' => 429],
                429,
                ['Retry-After' => (string) $limit->getRetryAfter()->getTimestamp()],
            );
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if ($idempotencyKey !== null) {
            $cached = $this->idempotency->find($idempotencyKey);
            if ($cached !== null) {
                return new JsonResponse($cached->body, $cached->statusCode);
            }
        }

        try {
            $transaction = $this->useCase->execute(
                new TransferMoneyCommand($payload->payer, $payload->payee, $payload->value),
            );
        } catch (TransferNotAuthorizedException $exception) {
            $this->metrics->incrementTransferOutcome('rejected');

            throw $exception;
        }

        $this->metrics->incrementTransferOutcome('completed');

        $body = $this->present($transaction);

        if ($idempotencyKey !== null) {
            $this->idempotency->remember($idempotencyKey, JsonResponse::HTTP_CREATED, $body);
        }

        return new JsonResponse($body, JsonResponse::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function present(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'status' => $transaction->status()->value,
            'value' => (string) $transaction->amount(),
            'payer' => $transaction->payer()->id(),
            'payee' => $transaction->payee()->id(),
            'createdAt' => $transaction->createdAt()->format(DATE_ATOM),
        ];
    }
}
