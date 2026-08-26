<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

use Ditto\NetSuiteClient\NetSuiteConfig;
use Firebase\JWT\JWT;

/**
 * Builds and signs the ES256 client-assertion JWT NetSuite's OAuth 2.0 Client
 * Credentials (M2M) grant exchanges for a bearer token. The JWT header's `kid`
 * must match the Certificate ID uploaded to the NetSuite integration record.
 */
final class JwtBuilder
{
    public function __construct(
        private readonly NetSuiteConfig $config,
        private readonly int $lifetimeSeconds = 300,
    ) {
    }

    public function build(string $audience, ?int $issuedAt = null): string
    {
        $issuedAt ??= time();

        $payload = [
            'iss' => $this->config->clientId,
            'scope' => $this->config->scopes,
            'aud' => $audience,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $this->lifetimeSeconds,
        ];

        $privateKey = file_get_contents($this->config->privateKeyPath);

        return JWT::encode($payload, $privateKey, 'ES256', $this->config->certificateId);
    }
}
