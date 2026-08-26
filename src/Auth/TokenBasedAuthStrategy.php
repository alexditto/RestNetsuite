<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

use Ditto\NetSuiteClient\NetSuiteConfig;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Signs each outgoing request per-request using NetSuite's Token-Based Authentication
 * (OAuth 1.0a-style HMAC signing), rather than exchanging for a cached bearer token.
 */
final class TokenBasedAuthStrategy implements AuthStrategy
{
    private const SIGNATURE_METHODS = [
        'sha1' => 'HMAC-SHA1',
        'sha256' => 'HMAC-SHA256',
    ];

    private readonly \Closure $timestampProvider;
    private readonly \Closure $nonceProvider;

    public function __construct(
        private readonly NetSuiteConfig $config,
        ?\Closure $timestampProvider = null,
        ?\Closure $nonceProvider = null,
    ) {
        if ($config->authMethod !== AuthMethod::TokenBasedAuth) {
            throw new InvalidArgumentException(
                'TokenBasedAuthStrategy requires a NetSuiteConfig configured with AuthMethod::TokenBasedAuth.',
            );
        }

        $this->timestampProvider = $timestampProvider ?? static fn (): int => time();
        $this->nonceProvider = $nonceProvider ?? static fn (): string => bin2hex(random_bytes(16));
    }

    public function applyToRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', $this->buildAuthorizationHeader($request));
    }

    private function buildAuthorizationHeader(RequestInterface $request): string
    {
        $signatureMethod = self::SIGNATURE_METHODS[$this->config->tbaHashAlgorithm];

        $oauthParams = [
            'oauth_consumer_key' => $this->config->consumerKey,
            'oauth_token' => $this->config->tokenId,
            'oauth_signature_method' => $signatureMethod,
            'oauth_timestamp' => (string) ($this->timestampProvider)(),
            'oauth_nonce' => ($this->nonceProvider)(),
            'oauth_version' => '1.0',
        ];

        $headerParams = ['realm' => $this->config->accountId]
            + $oauthParams
            + ['oauth_signature' => $this->sign($request, $oauthParams, $signatureMethod)];

        $pairs = [];
        foreach ($headerParams as $key => $value) {
            $pairs[] = sprintf('%s="%s"', $key, rawurlencode((string) $value));
        }

        return 'OAuth ' . implode(', ', $pairs);
    }

    /**
     * @param array<string, string> $oauthParams
     */
    private function sign(RequestInterface $request, array $oauthParams, string $signatureMethod): string
    {
        $uri = $request->getUri();
        $baseUrl = sprintf('%s://%s%s', $uri->getScheme(), $uri->getHost(), $uri->getPath());

        parse_str($uri->getQuery(), $queryParams);
        $allParams = array_merge($queryParams, $oauthParams);

        $pairs = [];
        foreach ($allParams as $key => $value) {
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        sort($pairs, SORT_STRING);

        $baseString = strtoupper($request->getMethod())
            . '&' . rawurlencode($baseUrl)
            . '&' . rawurlencode(implode('&', $pairs));

        $signingKey = rawurlencode((string) $this->config->consumerSecret)
            . '&' . rawurlencode((string) $this->config->tokenSecret);

        $algo = $signatureMethod === 'HMAC-SHA256' ? 'sha256' : 'sha1';

        return base64_encode(hash_hmac($algo, $baseString, $signingKey, true));
    }
}
