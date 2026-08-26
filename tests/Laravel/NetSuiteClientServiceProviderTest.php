<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Laravel;

use Ditto\NetSuiteClient\Auth\AuthStrategy;
use Ditto\NetSuiteClient\Auth\OAuth2Strategy;
use Ditto\NetSuiteClient\Auth\TokenBasedAuthStrategy;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\Laravel\NetSuiteClientServiceProvider;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\RecordClient;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use Orchestra\Testbench\TestCase;

final class NetSuiteClientServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NetSuiteClientServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('netsuite-client.account_id', '1234567_SB1');
        $app['config']->set('netsuite-client.auth_method', 'tba');
        $app['config']->set('netsuite-client.tba.consumer_key', 'consumer-key');
        $app['config']->set('netsuite-client.tba.consumer_secret', 'consumer-secret');
        $app['config']->set('netsuite-client.tba.token_id', 'token-id');
        $app['config']->set('netsuite-client.tba.token_secret', 'token-secret');
    }

    public function test_it_binds_net_suite_config_as_a_singleton(): void
    {
        $config = $this->app->make(NetSuiteConfig::class);

        $this->assertInstanceOf(NetSuiteConfig::class, $config);
        $this->assertSame($config, $this->app->make(NetSuiteConfig::class));
        $this->assertSame('1234567_SB1', $config->accountId);
        $this->assertSame('consumer-key', $config->consumerKey);
        $this->assertSame(NetSuiteConfig::DEFAULT_MAX_ATTEMPTS, $config->maxAttempts);
    }

    public function test_it_reads_max_attempts_from_config(): void
    {
        $this->app['config']->set('netsuite-client.max_attempts', 7);
        $this->app->forgetInstance(NetSuiteConfig::class);

        $config = $this->app->make(NetSuiteConfig::class);

        $this->assertSame(7, $config->maxAttempts);
    }

    public function test_it_binds_the_full_record_client_dependency_chain(): void
    {
        $this->assertInstanceOf(EndpointBuilder::class, $this->app->make(EndpointBuilder::class));
        $this->assertInstanceOf(ResponseParser::class, $this->app->make(ResponseParser::class));
        $this->assertInstanceOf(TokenBasedAuthStrategy::class, $this->app->make(AuthStrategy::class));
        $this->assertInstanceOf(HttpClient::class, $this->app->make(HttpClient::class));

        $recordClient = $this->app->make(RecordClient::class);
        $this->assertInstanceOf(RecordClient::class, $recordClient);
        $this->assertSame($recordClient, $this->app->make(RecordClient::class));
    }

    public function test_it_resolves_an_oauth2_strategy_when_configured_for_oauth2(): void
    {
        $this->app['config']->set('netsuite-client.auth_method', 'oauth2');
        $this->app['config']->set('netsuite-client.oauth2.client_id', 'client-id');
        $this->app['config']->set('netsuite-client.oauth2.certificate_id', 'certificate-id');
        $this->app['config']->set(
            'netsuite-client.oauth2.private_key_path',
            __DIR__ . '/../Fixtures/es256-test-private-key.pem',
        );
        $this->app->forgetInstance(NetSuiteConfig::class);
        $this->app->forgetInstance(AuthStrategy::class);

        $this->assertInstanceOf(OAuth2Strategy::class, $this->app->make(AuthStrategy::class));
    }

    public function test_it_publishes_the_config_file(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'netsuite-client-config'])->run();

        $this->assertFileExists($this->app->configPath('netsuite-client.php'));

        @unlink($this->app->configPath('netsuite-client.php'));
    }
}
