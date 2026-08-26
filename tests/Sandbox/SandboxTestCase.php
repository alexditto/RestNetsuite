<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Sandbox;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\AuthStrategy;
use Ditto\NetSuiteClient\Auth\JwtBuilder;
use Ditto\NetSuiteClient\Auth\OAuth2Strategy;
use Ditto\NetSuiteClient\Auth\TokenBasedAuthStrategy;
use Ditto\NetSuiteClient\Auth\TokenManager;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\RecordClient;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base class for tests that make real HTTP calls to a live NetSuite sandbox account.
 * Never run as part of the default `vendor/bin/phpunit` — phpunit.xml.dist excludes
 * tests/Sandbox entirely; these only run via `composer test:sandbox`
 * (phpunit-sandbox.xml.dist). Subclasses live in tests/Sandbox/Tests/, which is
 * gitignored — see README.md in this directory for why and how to write one.
 *
 * This class itself is safe to commit: it contains no credentials, and refuses to run
 * against anything but environment=sandbox as a hard backstop beyond gitignore hygiene.
 */
abstract class SandboxTestCase extends TestCase
{
    private ?NetSuiteConfig $config = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('NETSUITE_ACCOUNT_ID') === false) {
            $this->markTestSkipped(
                'Sandbox tests require NETSUITE_* environment variables (see tests/Sandbox/README.md).',
            );
        }
    }

    protected function config(): NetSuiteConfig
    {
        return $this->config ??= $this->buildConfig();
    }

    protected function client(): RecordClient
    {
        $config = $this->config();

        return new RecordClient(
            new HttpClient($config, $this->authStrategy($config)),
            new EndpointBuilder($config),
            new ResponseParser(),
        );
    }

    private function buildConfig(): NetSuiteConfig
    {
        $authMethod = AuthMethod::from(getenv('NETSUITE_AUTH_METHOD') ?: AuthMethod::TokenBasedAuth->value);

        $config = new NetSuiteConfig(
            accountId: (string) getenv('NETSUITE_ACCOUNT_ID'),
            authMethod: $authMethod,
            clientId: self::envOrNull('NETSUITE_CLIENT_ID'),
            certificateId: self::envOrNull('NETSUITE_CERTIFICATE_ID'),
            privateKeyPath: self::envOrNull('NETSUITE_PRIVATE_KEY_PATH'),
            consumerKey: self::envOrNull('NETSUITE_CONSUMER_KEY'),
            consumerSecret: self::envOrNull('NETSUITE_CONSUMER_SECRET'),
            tokenId: self::envOrNull('NETSUITE_TOKEN_ID'),
            tokenSecret: self::envOrNull('NETSUITE_TOKEN_SECRET'),
            environment: getenv('NETSUITE_ENVIRONMENT') ?: NetSuiteConfig::ENV_SANDBOX,
        );

        if ($config->isProduction()) {
            throw new RuntimeException(
                'Refusing to run sandbox tests against a config with environment=production. '
                . 'These tests create, modify, and delete real records — set '
                . 'NETSUITE_ENVIRONMENT=sandbox in .env.',
            );
        }

        return $config;
    }

    private function authStrategy(NetSuiteConfig $config): AuthStrategy
    {
        return match ($config->authMethod) {
            AuthMethod::TokenBasedAuth => new TokenBasedAuthStrategy($config),
            AuthMethod::OAuth2 => new OAuth2Strategy(new TokenManager(
                config: $config,
                jwtBuilder: new JwtBuilder($config),
                endpointBuilder: new EndpointBuilder($config),
            )),
        };
    }

    private static function envOrNull(string $key): ?string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? null : $value;
    }
}
