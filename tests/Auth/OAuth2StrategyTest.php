<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Auth;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Auth\JwtBuilder;
use Ditto\NetSuiteClient\Auth\OAuth2Strategy;
use Ditto\NetSuiteClient\Auth\TokenManager;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class OAuth2StrategyTest extends TestCase
{
    public function test_it_applies_a_bearer_token_from_the_token_manager(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::OAuth2,
            clientId: 'client-id',
            certificateId: 'certificate-id',
            privateKeyPath: __DIR__ . '/../Fixtures/es256-test-private-key.pem',
        );

        $httpClient = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], json_encode([
                    'access_token' => 'the-access-token',
                    'expires_in' => 3600,
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

        $strategy = new OAuth2Strategy($tokenManager);
        $signed = $strategy->applyToRequest(new Request('GET', 'https://example.com/record/customer/1'));

        $this->assertSame('Bearer the-access-token', $signed->getHeaderLine('Authorization'));
    }
}
