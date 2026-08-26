<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

use Ditto\NetSuiteClient\Exceptions\NetSuiteAuthException;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Exchanges a JwtBuilder-signed assertion for a bearer token via NetSuite's OAuth 2.0
 * Client Credentials (M2M) token endpoint, caching the token (via PSR-16) until shortly
 * before it expires.
 */
final class TokenManager
{
    private const CACHE_KEY_PREFIX = 'netsuite-client:oauth2-token:';
    private const EXPIRY_BUFFER_SECONDS = 60;

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly NetSuiteConfig $config,
        private readonly JwtBuilder $jwtBuilder,
        private readonly EndpointBuilder $endpointBuilder,
        private readonly ?CacheInterface $cache = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        if ($config->authMethod !== AuthMethod::OAuth2) {
            throw new InvalidArgumentException(
                'TokenManager requires a NetSuiteConfig configured with AuthMethod::OAuth2.',
            );
        }

        $defaultFactory = ($httpClient !== null && $requestFactory !== null && $streamFactory !== null)
            ? null
            : new HttpFactory();

        $this->httpClient = $httpClient ?? new GuzzleClient();
        $this->requestFactory = $requestFactory ?? $defaultFactory;
        $this->streamFactory = $streamFactory ?? $defaultFactory;
    }

    public function getAccessToken(): string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $this->config->accountId;

        $cached = $this->cache?->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $expiresIn] = $this->requestAccessToken();

        $this->cache?->set($cacheKey, $token, max(0, $expiresIn - self::EXPIRY_BUFFER_SECONDS));

        return $token;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function requestAccessToken(): array
    {
        $tokenUrl = $this->endpointBuilder->tokenUrl();
        $assertion = $this->jwtBuilder->build($tokenUrl);

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $assertion,
        ]);

        $request = $this->requestFactory
            ->createRequest('POST', $tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            throw new NetSuiteAuthException(
                'Failed to reach the NetSuite OAuth2 token endpoint: ' . $e->getMessage(),
            );
        }

        $payload = json_decode((string) $response->getBody(), true);
        $payload = is_array($payload) ? $payload : [];

        if ($response->getStatusCode() >= 400 || !isset($payload['access_token'])) {
            $message = $payload['error_description'] ?? $payload['error'] ?? (string) $response->getBody();

            throw new NetSuiteAuthException("NetSuite OAuth2 token request failed: {$message}");
        }

        return [$payload['access_token'], (int) ($payload['expires_in'] ?? 3600)];
    }
}
