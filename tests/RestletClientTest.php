<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Exceptions\NetSuiteValidationException;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\RestletMethod;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use Ditto\NetSuiteClient\RestletClient;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RestletClientTest extends TestCase
{
    private function fakeClient(ResponseInterface $response): ClientInterface
    {
        return new class ($response) implements ClientInterface {
            public ?RequestInterface $lastRequest = null;

            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return $this->response;
            }
        };
    }

    private function restletClient(ClientInterface $httpClient): RestletClient
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
        );

        return new RestletClient($httpClient, new EndpointBuilder($config), new ResponseParser());
    }

    public function test_call_sends_a_get_request_to_the_restlet_url_with_no_body(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode(['result' => 'ok'])));

        $result = $this->restletClient($client)->call('3489', '1', RestletMethod::Get);

        $this->assertSame('GET', $client->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=3489&deploy=1',
            (string) $client->lastRequest->getUri(),
        );
        $this->assertSame('', (string) $client->lastRequest->getBody());
        $this->assertSame(['result' => 'ok'], $result);
    }

    public function test_call_sends_a_post_request_with_a_json_body(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode(['created' => true])));

        $result = $this->restletClient($client)->call('3489', '1', RestletMethod::Post, ['foo' => 'bar']);

        $this->assertSame('POST', $client->lastRequest->getMethod());
        $this->assertSame('application/json', $client->lastRequest->getHeaderLine('Content-Type'));
        $this->assertSame(['foo' => 'bar'], json_decode((string) $client->lastRequest->getBody(), true));
        $this->assertSame(['created' => true], $result);
    }

    public function test_call_sends_a_put_request(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode(['updated' => true])));

        $this->restletClient($client)->call('3489', '1', RestletMethod::Put, ['foo' => 'bar']);

        $this->assertSame('PUT', $client->lastRequest->getMethod());
    }

    public function test_call_sends_a_delete_request(): void
    {
        $client = $this->fakeClient(new Response(204));

        $result = $this->restletClient($client)->call('3489', '1', RestletMethod::Delete);

        $this->assertSame('DELETE', $client->lastRequest->getMethod());
        $this->assertNull($result);
    }

    public function test_call_returns_the_raw_body_when_it_is_not_valid_json(): void
    {
        $client = $this->fakeClient(new Response(200, [], 'plain text response'));

        $result = $this->restletClient($client)->call('3489', '1', RestletMethod::Get);

        $this->assertSame('plain text response', $result);
    }

    public function test_call_throws_a_typed_exception_on_failure(): void
    {
        $client = $this->fakeClient(new Response(400, [], json_encode([
            'title' => 'Invalid request',
            'o:errorDetails' => [['detail' => 'Missing required parameter.']],
        ])));

        $this->expectException(NetSuiteValidationException::class);

        $this->restletClient($client)->call('3489', '1', RestletMethod::Post, []);
    }
}
