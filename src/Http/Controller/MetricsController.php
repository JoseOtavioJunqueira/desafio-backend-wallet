<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Infrastructure\Metrics\MetricsRecorder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class MetricsController
{
    public function __construct(private readonly MetricsRecorder $metrics)
    {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $lines = [
            '# HELP picpay_transfers_total Total number of transfer attempts, by outcome.',
            '# TYPE picpay_transfers_total counter',
        ];

        foreach ($this->metrics->transferCounts() as $outcome => $count) {
            $lines[] = sprintf('picpay_transfers_total{outcome="%s"} %d', $outcome, $count);
        }

        return new Response(implode("\n", $lines) . "\n", Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }
}
