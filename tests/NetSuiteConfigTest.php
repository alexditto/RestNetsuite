<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\NetSuiteConfig;
use InvalidArgumentException;

final class NetSuiteConfigTest extends TestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = tempnam(sys_get_temp_dir(), 'ns-key-');
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_it_builds_an_oauth2_config_with_a_valid_environment(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'cert-id',
            privateKeyPath: $this->keyPath,
            environment: 'sandbox',
        );

        $this->assertFalse($config->isProduction());
    }

    public function test_it_rejects_an_invalid_environment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'cert-id',
            privateKeyPath: $this->keyPath,
            environment: 'staging',
        );
    }

    public function test_it_rejects_a_missing_private_key_file_for_oauth2(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'cert-id',
            privateKeyPath: '/nonexistent/path.pem',
        );
    }

    public function test_it_rejects_oauth2_missing_client_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::OAuth2,
            certificateId: 'cert-id',
            privateKeyPath: $this->keyPath,
        );
    }

    public function test_it_builds_a_tba_config(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            tokenId: 'token-id',
            tokenSecret: 'token-secret',
        );

        $this->assertSame('sha256', $config->tbaHashAlgorithm);
        $this->assertSame(NetSuiteConfig::DEFAULT_MAX_ATTEMPTS, $config->maxAttempts);
    }

    public function test_it_accepts_a_custom_max_attempts(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            tokenId: 'token-id',
            tokenSecret: 'token-secret',
            maxAttempts: 7,
        );

        $this->assertSame(7, $config->maxAttempts);
    }

    public function test_it_rejects_a_max_attempts_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            tokenId: 'token-id',
            tokenSecret: 'token-secret',
            maxAttempts: 0,
        );
    }

    public function test_it_rejects_tba_missing_token_secret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            tokenId: 'token-id',
        );
    }

    public function test_it_rejects_an_unsupported_tba_hash_algorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'consumer-key',
            consumerSecret: 'consumer-secret',
            tokenId: 'token-id',
            tokenSecret: 'token-secret',
            tbaHashAlgorithm: 'md5',
        );
    }
}
