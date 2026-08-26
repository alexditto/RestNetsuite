<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Auth;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\TokenBasedAuthStrategy;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;

final class TokenBasedAuthStrategyTest extends TestCase
{
    private function tbaConfig(): NetSuiteConfig
    {
        return new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'ck123',
            consumerSecret: 'cs456',
            tokenId: 'tk789',
            tokenSecret: 'ts000',
        );
    }

    private function fixedStrategy(NetSuiteConfig $config, int $timestamp, string $nonce): TokenBasedAuthStrategy
    {
        return new TokenBasedAuthStrategy(
            $config,
            timestampProvider: static fn (): int => $timestamp,
            nonceProvider: static fn (): string => $nonce,
        );
    }

    public function test_it_signs_a_request_with_no_query_params(): void
    {
        $strategy = $this->fixedStrategy($this->tbaConfig(), 1700000000, 'abc123nonce');

        $request = new Request(
            'GET',
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
        );
        $signed = $strategy->applyToRequest($request);
        $header = $signed->getHeaderLine('Authorization');

        $this->assertStringStartsWith('OAuth ', $header);
        $this->assertStringContainsString('realm="1234567_SB1"', $header);
        $this->assertStringContainsString('oauth_consumer_key="ck123"', $header);
        $this->assertStringContainsString('oauth_token="tk789"', $header);
        $this->assertStringContainsString('oauth_signature_method="HMAC-SHA256"', $header);
        $this->assertStringContainsString('oauth_timestamp="1700000000"', $header);
        $this->assertStringContainsString('oauth_nonce="abc123nonce"', $header);
        $this->assertStringContainsString('oauth_version="1.0"', $header);

        // Independently reconstruct the RFC 5849 signature base string (not by calling the
        // implementation) to actually verify the encoding/ordering logic, not just that some
        // signature was produced.
        $expectedBaseString = 'GET&' . rawurlencode(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
        ) . '&' . rawurlencode(implode('&', [
            'oauth_consumer_key=ck123',
            'oauth_nonce=abc123nonce',
            'oauth_signature_method=HMAC-SHA256',
            'oauth_timestamp=1700000000',
            'oauth_token=tk789',
            'oauth_version=1.0',
        ]));
        $expectedSigningKey = rawurlencode('cs456') . '&' . rawurlencode('ts000');
        $expectedSignature = base64_encode(hash_hmac('sha256', $expectedBaseString, $expectedSigningKey, true));

        $this->assertStringContainsString('oauth_signature="' . rawurlencode($expectedSignature) . '"', $header);
    }

    public function test_it_folds_query_parameters_into_the_signature(): void
    {
        $strategy = $this->fixedStrategy($this->tbaConfig(), 1700000000, 'abc123nonce');

        $withQuery = $strategy->applyToRequest(new Request(
            'GET',
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer?limit=10&offset=0',
        ));
        $withoutQuery = $strategy->applyToRequest(new Request(
            'GET',
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer',
        ));

        $this->assertNotSame(
            $withQuery->getHeaderLine('Authorization'),
            $withoutQuery->getHeaderLine('Authorization'),
        );
    }

    public function test_it_rejects_a_config_not_configured_for_tba(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $oauth2Config = new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'cert-id',
            privateKeyPath: tempnam(sys_get_temp_dir(), 'ns-key-'),
        );

        new TokenBasedAuthStrategy($oauth2Config);
    }
}
