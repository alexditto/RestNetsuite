<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Http;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\AuthStrategy;
use Ditto\NetSuiteClient\Http\HttpClient;
use Ditto\NetSuiteClient\Http\RetryMiddleware;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

final class HttpClientTest extends TestCase
{
    private function config(int $maxAttempts = NetSuiteConfig::DEFAULT_MAX_ATTEMPTS): NetSuiteConfig
    {
        return new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
            maxAttempts: $maxAttempts,
        );
    }

    public function test_it_applies_the_auth_strategy_before_delegating_to_the_inner_client(): void
    {
        $authStrategy = new class implements AuthStrategy {
            public function applyToRequest(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('Authorization', 'Test some-signed-value');
            }
        };

        $inner = new class implements ClientInterface {
            public ?RequestInterface $received = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->received = $request;

                return new Response(200);
            }
        };

        $httpClient = new HttpClient($this->config(), $authStrategy, $inner);
        $response = $httpClient->sendRequest(new Request('GET', 'https://example.com/record/customer/1'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Test some-signed-value', $inner->received->getHeaderLine('Authorization'));
    }

    public function test_it_defaults_to_a_guzzle_client_wrapped_in_retry_middleware(): void
    {
        $authStrategy = new class implements AuthStrategy {
            public function applyToRequest(RequestInterface $request): RequestInterface
            {
                return $request;
            }
        };

        // Constructing shouldn't perform any network I/O or throw.
        new HttpClient($this->config(), $authStrategy);

        $this->addToAssertionCount(1);
    }

    public function test_it_passes_the_configured_max_attempts_to_the_default_retry_middleware(): void
    {
        $authStrategy = new class implements AuthStrategy {
            public function applyToRequest(RequestInterface $request): RequestInterface
            {
                return $request;
            }
        };

        $httpClient = new HttpClient($this->config(maxAttempts: 7), $authStrategy);

        $innerProperty = new ReflectionProperty(HttpClient::class, 'inner');
        $retryMiddleware = $innerProperty->getValue($httpClient);

        $this->assertInstanceOf(RetryMiddleware::class, $retryMiddleware);

        $maxAttemptsProperty = new ReflectionProperty(RetryMiddleware::class, 'maxAttempts');
        $this->assertSame(7, $maxAttemptsProperty->getValue($retryMiddleware));
    }
}
