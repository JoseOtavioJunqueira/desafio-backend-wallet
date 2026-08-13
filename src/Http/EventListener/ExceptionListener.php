<?php

declare(strict_types=1);

namespace App\Http\EventListener;

use App\Domain\Exception\DomainExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Single place every exception funnels through on its way to an HTTP response, formatted as
 * RFC 7807 `application/problem+json`. Domain rule violations already carry their own status
 * code and machine-readable slug ({@see DomainExceptionInterface}); this listener only decides
 * the shape of the body and makes sure nothing above 4xx ever leaks internals in production.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        [$status, $problem] = $this->describe($exception);

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        } else {
            $this->logger->info($exception->getMessage(), ['status' => $status, 'exception_class' => $exception::class]);
        }

        $event->setResponse(new JsonResponse($problem, $status, ['Content-Type' => 'application/problem+json']));
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function describe(\Throwable $exception): array
    {
        if ($exception instanceof DomainExceptionInterface) {
            return [$exception->httpStatusCode(), [
                'type' => 'about:blank',
                'title' => $exception->errorCode(),
                'status' => $exception->httpStatusCode(),
                'detail' => $exception->getMessage(),
            ]];
        }

        $validationFailure = $this->findValidationFailure($exception);
        if ($validationFailure !== null) {
            $violations = [];
            foreach ($validationFailure->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return [422, [
                'type' => 'about:blank',
                'title' => 'validation_failed',
                'status' => 422,
                'detail' => 'One or more fields are invalid.',
                'violations' => $violations,
            ]];
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return [$status, [
                'type' => 'about:blank',
                'title' => self::titleForStatus($status),
                'status' => $status,
                'detail' => $exception->getMessage() !== '' ? $exception->getMessage() : self::titleForStatus($status),
            ]];
        }

        return [500, [
            'type' => 'about:blank',
            'title' => 'internal_server_error',
            'status' => 500,
            'detail' => $this->debug ? $exception->getMessage() : 'An unexpected error occurred.',
        ]];
    }

    private function findValidationFailure(\Throwable $exception): ?ValidationFailedException
    {
        $current = $exception;

        while ($current !== null) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    private static function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            404 => 'not_found',
            405 => 'method_not_allowed',
            429 => 'too_many_requests',
            default => 'error',
        };
    }
}
