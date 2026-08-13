<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Predis\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deliberately tiny: three atomic Redis counters, exposed as Prometheus text format by
 * App\Http\Controller\MetricsController. A full metrics/tracing stack (histograms, latency
 * buckets, OpenTelemetry) is real future work, not something worth building bespoke here — see
 * docs/adr/0005-observability-scope.md.
 */
final class MetricsRecorder
{
    private const string KEY_PREFIX = 'picpay:metrics:transfers:';

    public function __construct(
        #[Autowire(service: 'app.redis_client')]
        private readonly ClientInterface $redis,
    ) {
    }

    public function incrementTransferOutcome(string $outcome): void
    {
        $this->redis->incr(self::KEY_PREFIX . $outcome);
    }

    /** @return array<string, int> */
    public function transferCounts(): array
    {
        return [
            'completed' => (int) ($this->redis->get(self::KEY_PREFIX . 'completed') ?? 0),
            'rejected' => (int) ($this->redis->get(self::KEY_PREFIX . 'rejected') ?? 0),
        ];
    }
}
