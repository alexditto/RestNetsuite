<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient;

use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Http\RestletMethod;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Calls a NetSuite RESTlet (identified by script + deploy internal ids) with the given
 * HTTP method and an optional JSON body. Unlike RecordClient, a RESTlet's response shape
 * is whatever the underlying SuiteScript returns, not a fixed record/collection
 * structure — so the decoded JSON (or raw string, if the body isn't valid JSON) is
 * returned as-is rather than mapped onto NetSuiteRecord/NetSuiteCollection.
 */
final class RestletClient
{
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly EndpointBuilder $endpointBuilder,
        private readonly ResponseParser  $responseParser,
        ?RequestFactoryInterface         $requestFactory = null,
        ?StreamFactoryInterface          $streamFactory = null,
    )
    {
        $defaultFactory = ($requestFactory !== null && $streamFactory !== null) ? null : new HttpFactory();

        $this->requestFactory = $requestFactory ?? $defaultFactory;
        $this->streamFactory = $streamFactory ?? $defaultFactory;
    }

    /**
     * @param array<int|string, mixed>|null $body
     */
    public function call(string $script, string $deploy, RestletMethod $method, ?array $body = null): mixed
    {
        $request = $this->requestFactory
            ->createRequest($method->value, $this->endpointBuilder->restletUrl($script, $deploy))
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($body)));
        }

        $response = $this->httpClient->sendRequest($request);

        $this->responseParser->assertSuccessful($response);

        return $this->decodeBody($response);
    }

    private function decodeBody(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}
