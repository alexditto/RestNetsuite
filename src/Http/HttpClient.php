<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Ditto\NetSuiteClient\Auth\AuthStrategy;
use Ditto\NetSuiteClient\NetSuiteConfig;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Applies the configured AuthStrategy to every outgoing request, then delegates to an
 * inner PSR-18 client (Guzzle wrapped in RetryMiddleware by default, swappable).
 */
final class HttpClient implements ClientInterface
{
    private readonly ClientInterface $inner;

    public function __construct(
        private readonly NetSuiteConfig $config,
        private readonly AuthStrategy $authStrategy,
        ?ClientInterface $inner = null,
    ) {
        $this->inner = $inner ?? new RetryMiddleware(new GuzzleClient(), maxAttempts: $config->maxAttempts);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->inner->sendRequest($this->authStrategy->applyToRequest($request));
    }
}
