<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Authorizer;

use App\Application\Port\AuthorizerGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls the mock authorizer (`GET https://util.devi.tools/api/v2/authorize`), which answers:
 *
 *     { "status": "success", "data": { "authorization": true } }
 *     { "status": "fail",    "data": { "authorization": false } }
 *
 * verified by hand against the live mock. Any transport error, timeout, non-2xx status, or
 * unparseable body is treated as "not authorized" (fail-closed) and logged — it must never
 * throw out into the use case, which would abort the surrounding DB transaction on the wrong
 * kind of failure and turn a "the mock is flaky" incident into a 500 instead of a clean 403.
 */
final class HttpAuthorizerGateway implements AuthorizerGatewayInterface
{
    public function __construct(
        #[Autowire(service: 'app.http_client.authorizer')]
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function authorize(): bool
    {
        try {
            $response = $this->httpClient->request('GET', '');
            $payload = $response->toArray(false);
        } catch (\Throwable $exception) {
            $this->logger->warning('Authorizer service call failed; denying transfer by default.', [
                'exception' => $exception,
            ]);

            return false;
        }

        $data = $payload['data'] ?? null;
        $authorized = is_array($data) ? ($data['authorization'] ?? null) : null;

        if (!is_bool($authorized)) {
            $this->logger->warning('Authorizer service returned an unexpected payload; denying transfer by default.', [
                'payload' => $payload,
            ]);

            return false;
        }

        return $authorized;
    }
}
