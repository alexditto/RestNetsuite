<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient;

use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\Responses\NetSuiteCollection;
use Ditto\NetSuiteClient\Responses\NetSuiteRecord;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Generic CRUD + SuiteQL access to any NetSuite record type, standard or custom. Thin
 * orchestration on top of an already-authenticated PSR-18 client (typically
 * Http\HttpClient), EndpointBuilder, and ResponseParser — no record-type-specific logic.
 */
final class RecordClient
{
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly EndpointBuilder $endpointBuilder,
        private readonly ResponseParser $responseParser,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $defaultFactory = ($requestFactory !== null && $streamFactory !== null) ? null : new HttpFactory();

        $this->requestFactory = $requestFactory ?? $defaultFactory;
        $this->streamFactory = $streamFactory ?? $defaultFactory;
    }

    public function get(string $recordType, string $id): NetSuiteRecord
    {
        $request = $this->requestFactory
            ->createRequest('GET', $this->endpointBuilder->recordUrl($recordType, $id))
            ->withHeader('Accept', 'application/json');

        $response = $this->httpClient->sendRequest($request);

        return $this->responseParser->parseRecord($response, $recordType);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function create(string $recordType, array $fields): NetSuiteRecord
    {
        $request = $this->jsonRequest('POST', $this->endpointBuilder->recordUrl($recordType), $fields);
        $response = $this->httpClient->sendRequest($request);

        return $this->recordFromWriteResponse($response, $recordType, $fields, id: null);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(string $recordType, string $id, array $fields): NetSuiteRecord
    {
        $request = $this->jsonRequest('PATCH', $this->endpointBuilder->recordUrl($recordType, $id), $fields);
        $response = $this->httpClient->sendRequest($request);

        return $this->recordFromWriteResponse($response, $recordType, $fields, $id);
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function replace(string $recordType, string $id, array $fields): NetSuiteRecord
    {
        $request = $this->jsonRequest('PUT', $this->endpointBuilder->recordUrl($recordType, $id), $fields);
        $response = $this->httpClient->sendRequest($request);

        return $this->recordFromWriteResponse($response, $recordType, $fields, $id);
    }

    public function delete(string $recordType, string $id): void
    {
        $request = $this->requestFactory->createRequest('DELETE', $this->endpointBuilder->recordUrl($recordType, $id));
        $response = $this->httpClient->sendRequest($request);

        $this->responseParser->assertSuccessful($response);
    }

    public function query(string $suiteQl, int $limit = 1000, int $offset = 0): NetSuiteCollection
    {
        // NetSuite requires this header on SuiteQL requests; without it the endpoint
        // returns 400 INVALID_HEADER ("The required request header 'Prefer' is missing").
        $request = $this->jsonRequest('POST', $this->endpointBuilder->queryUrl($limit, $offset), ['q' => $suiteQl])
            ->withHeader('Prefer', 'transient');
        $response = $this->httpClient->sendRequest($request);

        return $this->responseParser->parseCollection($response);
    }

    /**
     * NetSuite's record write endpoints (POST/PATCH/PUT) return 204 No Content with a
     * `Location` header on success, not the record body — so there's nothing to parse.
     * The returned NetSuiteRecord reflects the id plus the fields that were submitted,
     * not the full server-side state; call get() afterward if the canonical record
     * (e.g. server-computed fields) is needed.
     *
     * @param array<string, mixed> $submittedFields
     */
    private function recordFromWriteResponse(
        ResponseInterface $response,
        string $recordType,
        array $submittedFields,
        ?string $id,
    ): NetSuiteRecord {
        $this->responseParser->assertSuccessful($response);

        if ((string) $response->getBody() !== '') {
            return $this->responseParser->parseRecord($response, $recordType);
        }

        return new NetSuiteRecord($recordType, $id ?? $this->extractIdFromLocation($response), $submittedFields);
    }

    private function extractIdFromLocation(ResponseInterface $response): ?string
    {
        $location = $response->getHeaderLine('Location');
        if ($location === '') {
            return null;
        }

        $path = (string) parse_url($location, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', rtrim($path, '/'))));

        return $segments === [] ? null : (string) end($segments);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(string $method, string $url, array $body): RequestInterface
    {
        return $this->requestFactory
            ->createRequest($method, $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($body)));
    }
}
