<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 decorator that reports every attempt (request + outcome) to a RequestLogger
 * before returning the response or rethrowing, unchanged, to the caller. Placed
 * innermost in the chain (HttpClient -> RetryMiddleware -> LoggingMiddleware -> raw
 * client) so each retried attempt is logged individually, not just the final outcome.
 */
final class LoggingMiddleware implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly RequestLogger $logger,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->inner->sendRequest($request);
        } catch (\Throwable $exception) {
            $this->logger->log($request, null, $exception);

            throw $exception;
        }

        $this->logger->log($request, $response, null);

        return $response;
    }
}
