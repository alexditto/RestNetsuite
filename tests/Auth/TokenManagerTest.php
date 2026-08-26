<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Auth;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\JwtBuilder;
use Ditto\NetSuiteClient\Auth\TokenManager;
use Ditto\NetSuiteClient\Exceptions\NetSuiteAuthException;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

final class TokenManagerTest extends TestCase
{
    private function config(): NetSuiteConfig
    {
        return new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'certificate-id',
            privateKeyPath: __DIR__ . '/../Fixtures/es256-test-private-key.pem',
        );
    }

    public function test_it_requests_and_caches_an_access_token(): void
    {
        $config = $this->config();

        $httpClient = new class implements ClientInterface {
            public int $callCount = 0;
            public ?RequestInterface $lastRequest = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                $this->lastRequest = $request;

                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'token_type' => 'Bearer',
                    'access_token' => 'access-token-1',
                    'expires_in' => 3600,
                ]));
            }
        };

        $cache = new class implements CacheInterface {
            private array $store = [];

            public function get($key, $default = null): mixed
            {
                return $this->store[$key] ?? $default;
            }

            public function set($key, $value, $ttl = null): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function delete($key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function clear(): bool
            {
                $this->store = [];

                return true;
            }

            public function getMultiple($keys, $default = null): iterable
            {
                return array_map(fn ($k) => $this->get($k, $default), (array) $keys);
            }

            public function setMultiple($values, $ttl = null): bool
            {
                foreach ($values as $k => $v) {
                    $this->set($k, $v, $ttl);
                }

                return true;
            }

            public function deleteMultiple($keys): bool
            {
                foreach ($keys as $k) {
                    $this->delete($k);
                }

                return true;
            }

            public function has($key): bool
            {
                return array_key_exists($key, $this->store);
            }
        };

        $httpFactory = new HttpFactory();
        $tokenManager = new TokenManager(
            config: $config,
            jwtBuilder: new JwtBuilder($config),
            endpointBuilder: new EndpointBuilder($config),
            cache: $cache,
            httpClient: $httpClient,
            requestFactory: $httpFactory,
            streamFactory: $httpFactory,
        );

        $token1 = $tokenManager->getAccessToken();
        $token2 = $tokenManager->getAccessToken();

        $this->assertSame('access-token-1', $token1);
        $this->assertSame('access-token-1', $token2);
        $this->assertSame(1, $httpClient->callCount, 'second call should be served from cache');

        $this->assertSame('POST', $httpClient->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/auth/oauth2/v1/token',
            (string) $httpClient->lastRequest->getUri(),
        );

        $body = (string) $httpClient->lastRequest->getBody();
        $this->assertStringContainsString('grant_type=client_credentials', $body);
        $this->assertStringContainsString(
            'client_assertion_type=urn%3Aietf%3Aparams%3Aoauth%3Aclient-assertion-type%3Ajwt-bearer',
            $body,
        );
        $this->assertStringContainsString('client_assertion=', $body);
    }

    public function test_it_throws_a_net_suite_auth_exception_on_failure_response(): void
    {
        $config = $this->config();

        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                    'error' => 'invalid_client',
                    'error_description' => 'JWT signature verification failed',
                ]));
            }
        };

        $httpFactory = new HttpFactory();
        $tokenManager = new TokenManager(
            config: $config,
            jwtBuilder: new JwtBuilder($config),
            endpointBuilder: new EndpointBuilder($config),
            httpClient: $httpClient,
            requestFactory: $httpFactory,
            streamFactory: $httpFactory,
        );

        $this->expectException(NetSuiteAuthException::class);
        $this->expectExceptionMessage('JWT signature verification failed');

        $tokenManager->getAccessToken();
    }

    public function test_it_rejects_a_config_not_configured_for_oauth2(): void
    {
        $tbaConfig = new NetSuiteConfig(
            accountId: '1234567',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
        );

        $this->expectException(InvalidArgumentException::class);

        new TokenManager(
            config: $tbaConfig,
            jwtBuilder: new JwtBuilder($tbaConfig),
            endpointBuilder: new EndpointBuilder($tbaConfig),
        );
    }
}
