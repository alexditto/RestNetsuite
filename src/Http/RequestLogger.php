<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Observes a single HTTP attempt made by LoggingMiddleware. Exactly one of $response
 * or $exception is non-null: a response means the request reached NetSuite and got an
 * HTTP response (any status code); an exception means the transport itself failed
 * (network error, timeout, etc.) before any response was received.
 */
interface RequestLogger
{
    public function log(RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): void;
}
