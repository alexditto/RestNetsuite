<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Auth;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\JwtBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtBuilderTest extends TestCase
{
    public function test_it_builds_a_signed_es256_jwt_with_the_expected_claims(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'certificate-id',
            privateKeyPath: __DIR__ . '/../Fixtures/es256-test-private-key.pem',
            scopes: ['rest_webservices'],
        );

        $now = time();
        $jwt = (new JwtBuilder($config, lifetimeSeconds: 120))->build(
            'https://example.com/token',
            issuedAt: $now,
        );

        $header = json_decode($this->base64UrlDecode(explode('.', $jwt)[0]), true);
        $this->assertSame('ES256', $header['alg']);
        $this->assertSame('certificate-id', $header['kid']);

        // Decoding with the matching public key both proves the signature is valid
        // ES256 and gives us the claims to check, independent of build()'s internals.
        $publicKeyPem = file_get_contents(__DIR__ . '/../Fixtures/es256-test-public-key.pem');
        $claims = JWT::decode($jwt, new Key($publicKeyPem, 'ES256'));

        $this->assertSame('client-id', $claims->iss);
        $this->assertSame(['rest_webservices'], $claims->scope);
        $this->assertSame('https://example.com/token', $claims->aud);
        $this->assertSame($now, $claims->iat);
        $this->assertSame($now + 120, $claims->exp);
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
