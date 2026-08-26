<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 decorator that retries 429 responses with exponential backoff, honoring
 * `Retry-After` when NetSuite sends one. Does not retry other status codes or network
 * exceptions, and does not throw on final failure — it just returns the last response,
 * leaving interpretation (e.g. mapping a persisting 429 to an exception) to the caller.
 */
final class RetryMiddleware implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly int $maxAttempts = 5,
        private readonly float $baseDelaySeconds = 1.0,
        private readonly float $maxDelaySeconds = 30.0,
        private readonly ?\Closure $sleeper = null,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $response = $this->inner->sendRequest($request);

            if ($response->getStatusCode() !== 429 || $attempt >= $this->maxAttempts) {
                return $response;
            }

            $this->sleep($this->resolveDelaySeconds($response, $attempt));
        }
    }

    private function resolveDelaySeconds(ResponseInterface $response, int $attempt): float
    {
        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter !== '') {
            if (is_numeric($retryAfter)) {
                return min((float) $retryAfter, $this->maxDelaySeconds);
            }

            $retryAfterTimestamp = strtotime($retryAfter);
            if ($retryAfterTimestamp !== false) {
                return min((float) max(0, $retryAfterTimestamp - time()), $this->maxDelaySeconds);
            }
        }

        return min($this->baseDelaySeconds * (2 ** ($attempt - 1)), $this->maxDelaySeconds);
    }

    private function sleep(float $seconds): void
    {
        if ($this->sleeper !== null) {
            ($this->sleeper)($seconds);

            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }
}
