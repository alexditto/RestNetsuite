<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Laravel;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\AuthStrategy;
use Ditto\NetSuiteClient\Auth\JwtBuilder;
use Ditto\NetSuiteClient\Auth\OAuth2Strategy;
use Ditto\NetSuiteClient\Auth\TokenBasedAuthStrategy;
use Ditto\NetSuiteClient\Auth\TokenManager;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\FileRequestLogger;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\Http\LoggingMiddleware;
use Ditto\NetSuiteClient\Http\RetryMiddleware;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\RecordClient;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\SimpleCache\CacheInterface;

final class NetSuiteClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/netsuite-client.php', 'netsuite-client');

        $this->app->singleton(NetSuiteConfig::class, function (): NetSuiteConfig {
            $config = $this->app['config']->get('netsuite-client');

            return new NetSuiteConfig(
                accountId: $config['account_id'],
                authMethod: AuthMethod::from($config['auth_method']),
                clientId: $config['oauth2']['client_id'] ?? null,
                certificateId: $config['oauth2']['certificate_id'] ?? null,
                privateKeyPath: $config['oauth2']['private_key_path'] ?? null,
                consumerKey: $config['tba']['consumer_key'] ?? null,
                consumerSecret: $config['tba']['consumer_secret'] ?? null,
                tokenId: $config['tba']['token_id'] ?? null,
                tokenSecret: $config['tba']['token_secret'] ?? null,
                environment: $config['environment'] ?? NetSuiteConfig::ENV_SANDBOX,
                scopes: $config['scopes'] ?? ['restlets', 'rest_webservices'],
                tbaHashAlgorithm: $config['tba']['hash_algorithm'] ?? 'sha256',
                maxAttempts: $config['max_attempts'] ?? NetSuiteConfig::DEFAULT_MAX_ATTEMPTS,
            );
        });

        $this->app->singleton(EndpointBuilder::class, fn (Application $app): EndpointBuilder => new EndpointBuilder(
            $app->make(NetSuiteConfig::class),
        ));

        $this->app->singleton(ResponseParser::class, fn (): ResponseParser => new ResponseParser());

        $this->app->singleton(AuthStrategy::class, function (Application $app): AuthStrategy {
            $config = $app->make(NetSuiteConfig::class);

            return match ($config->authMethod) {
                AuthMethod::TokenBasedAuth => new TokenBasedAuthStrategy($config),
                AuthMethod::OAuth2 => new OAuth2Strategy(new TokenManager(
                    config: $config,
                    jwtBuilder: new JwtBuilder($config),
                    endpointBuilder: $app->make(EndpointBuilder::class),
                    cache: $app->make(CacheInterface::class),
                )),
            };
        });

        $this->app->singleton(HttpClient::class, function (Application $app): HttpClient {
            $config = $app->make(NetSuiteConfig::class);
            $logging = $app['config']->get('netsuite-client.logging', []);

            $inner = ($logging['enabled'] ?? false)
                ? new RetryMiddleware(
                    new LoggingMiddleware(new GuzzleClient(), new FileRequestLogger($logging['path'])),
                    maxAttempts: $config->maxAttempts,
                )
                : null;

            return new HttpClient($config, $app->make(AuthStrategy::class), $inner);
        });

        $this->app->singleton(RecordClient::class, fn (Application $app): RecordClient => new RecordClient(
            $app->make(HttpClient::class),
            $app->make(EndpointBuilder::class),
            $app->make(ResponseParser::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/netsuite-client.php' => $this->app->configPath('netsuite-client.php'),
            ], 'netsuite-client-config');
        }
    }
}
