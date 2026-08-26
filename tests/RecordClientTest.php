<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Exceptions\NetSuiteNotFoundException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteValidationException;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\RecordClient;
use Ditto\NetSuiteClient\Responses\NetSuiteCollection;
use Ditto\NetSuiteClient\Responses\NetSuiteRecord;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordClientTest extends TestCase
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

    private function recordClient(ClientInterface $httpClient): RecordClient
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
        );

        return new RecordClient($httpClient, new EndpointBuilder($config), new ResponseParser());
    }

    public function test_get_sends_a_get_request_and_returns_a_parsed_record(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode([
            'id' => '42',
            'entityid' => 'Acme Co',
        ])));

        $record = $this->recordClient($client)->get('customer', '42');

        $this->assertSame('GET', $client->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
            (string) $client->lastRequest->getUri(),
        );
        $this->assertInstanceOf(NetSuiteRecord::class, $record);
        $this->assertSame('42', $record->id);
        $this->assertSame('Acme Co', $record->get('entityid'));
    }

    public function test_get_throws_a_typed_exception_on_404(): void
    {
        $client = $this->fakeClient(new Response(404, [], json_encode(['title' => 'Record not found.'])));

        $this->expectException(NetSuiteNotFoundException::class);

        $this->recordClient($client)->get('customer', 'missing');
    }

    public function test_create_sends_a_post_with_a_json_body_and_builds_a_record_from_the_location_header(): void
    {
        $client = $this->fakeClient(new Response(204, [
            'Location' => 'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/99',
        ]));

        $record = $this->recordClient($client)->create('customer', ['entityid' => 'New Co']);

        $this->assertSame('POST', $client->lastRequest->getMethod());
        $this->assertSame('application/json', $client->lastRequest->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['entityid' => 'New Co'],
            json_decode((string) $client->lastRequest->getBody(), true),
        );
        $this->assertSame('99', $record->id);
        $this->assertSame('New Co', $record->get('entityid'));
    }

    public function test_create_parses_the_body_when_the_response_has_one(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode([
            'id' => '99',
            'entityid' => 'New Co',
            'balance' => 0,
        ])));

        $record = $this->recordClient($client)->create('customer', ['entityid' => 'New Co']);

        $this->assertSame('99', $record->id);
        $this->assertSame(0, $record->get('balance'));
    }

    public function test_create_throws_a_typed_exception_on_validation_failure(): void
    {
        $client = $this->fakeClient(new Response(400, [], json_encode([
            'title' => 'Missing required field',
            'o:errorDetails' => [['detail' => 'entityid is required.']],
        ])));

        $this->expectException(NetSuiteValidationException::class);

        $this->recordClient($client)->create('customer', []);
    }

    public function test_update_sends_a_patch_request(): void
    {
        $client = $this->fakeClient(new Response(204));

        $record = $this->recordClient($client)->update('customer', '42', ['entityid' => 'Updated Co']);

        $this->assertSame('PATCH', $client->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
            (string) $client->lastRequest->getUri(),
        );
        $this->assertSame('42', $record->id);
        $this->assertSame('Updated Co', $record->get('entityid'));
    }

    public function test_replace_sends_a_put_request(): void
    {
        $client = $this->fakeClient(new Response(204));

        $this->recordClient($client)->replace('customer', '42', ['entityid' => 'Replaced Co']);

        $this->assertSame('PUT', $client->lastRequest->getMethod());
    }

    public function test_delete_sends_a_delete_request_and_returns_void(): void
    {
        $client = $this->fakeClient(new Response(204));

        $result = $this->recordClient($client)->delete('customer', '42');

        $this->assertNull($result);
        $this->assertSame('DELETE', $client->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
            (string) $client->lastRequest->getUri(),
        );
    }

    public function test_delete_throws_a_typed_exception_on_failure(): void
    {
        $client = $this->fakeClient(new Response(400, [], json_encode([
            'title' => 'Cannot delete',
            'o:errorDetails' => [['detail' => 'Record is referenced elsewhere.']],
        ])));

        $this->expectException(NetSuiteValidationException::class);

        $this->recordClient($client)->delete('customer', '42');
    }

    public function test_query_sends_a_suiteql_post_and_returns_a_collection(): void
    {
        $client = $this->fakeClient(new Response(200, [], json_encode([
            'count' => 1,
            'hasMore' => false,
            'items' => [['id' => '1']],
            'offset' => 0,
            'totalResults' => 1,
        ])));

        $collection = $this->recordClient($client)->query('SELECT id FROM customer', limit: 10, offset: 5);

        $this->assertSame('POST', $client->lastRequest->getMethod());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/query/v1/suiteql?limit=10&offset=5',
            (string) $client->lastRequest->getUri(),
        );
        $this->assertSame(
            ['q' => 'SELECT id FROM customer'],
            json_decode((string) $client->lastRequest->getBody(), true),
        );
        $this->assertSame('transient', $client->lastRequest->getHeaderLine('Prefer'));
        $this->assertInstanceOf(NetSuiteCollection::class, $collection);
        $this->assertCount(1, $collection);
    }
}
