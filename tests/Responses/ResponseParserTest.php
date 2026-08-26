<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Responses;

use Ditto\NetSuiteClient\Exceptions\NetSuiteAuthException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteNotFoundException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteRateLimitException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteServerException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteValidationException;
use Ditto\NetSuiteClient\Responses\NetSuiteCollection;
use Ditto\NetSuiteClient\Responses\NetSuiteRecord;
use Ditto\NetSuiteClient\Responses\ResponseParser;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Response;

final class ResponseParserTest extends TestCase
{
    private function problemResponse(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, $headers, json_encode($body));
    }

    public function test_it_parses_a_successful_record_response(): void
    {
        $response = new Response(200, [], json_encode([
            'id' => '123',
            'entityid' => 'Acme Co',
            'links' => [['rel' => 'self', 'href' => 'https://example.com/customer/123']],
        ]));

        $record = (new ResponseParser())->parseRecord($response, 'customer');

        $this->assertInstanceOf(NetSuiteRecord::class, $record);
        $this->assertSame('customer', $record->recordType);
        $this->assertSame('123', $record->id);
        $this->assertSame('Acme Co', $record->get('entityid'));
        $this->assertArrayNotHasKey('links', $record->toArray());
        $this->assertSame([['rel' => 'self', 'href' => 'https://example.com/customer/123']], $record->links);
    }

    public function test_it_parses_a_record_response_without_an_id(): void
    {
        $response = new Response(200, [], json_encode(['entityid' => 'Acme Co']));

        $record = (new ResponseParser())->parseRecord($response, 'customer');

        $this->assertNull($record->id);
    }

    public function test_it_parses_a_successful_collection_response(): void
    {
        $response = new Response(200, [], json_encode([
            'links' => [['rel' => 'next', 'href' => 'https://example.com/next']],
            'count' => 2,
            'hasMore' => true,
            'items' => [['id' => '1'], ['id' => '2']],
            'offset' => 0,
            'totalResults' => 10,
        ]));

        $collection = (new ResponseParser())->parseCollection($response);

        $this->assertInstanceOf(NetSuiteCollection::class, $collection);
        $this->assertCount(2, $collection);
        $this->assertTrue($collection->hasMore);
        $this->assertSame(10, $collection->totalResults);
    }

    public function test_assert_successful_passes_through_2xx_and_no_content_responses(): void
    {
        $parser = new ResponseParser();

        $parser->assertSuccessful(new Response(200));
        $parser->assertSuccessful(new Response(204));

        $this->addToAssertionCount(2);
    }

    public function test_it_maps_401_to_auth_exception(): void
    {
        $this->expectException(NetSuiteAuthException::class);
        $this->expectExceptionMessage('Invalid login attempt.');

        (new ResponseParser())->assertSuccessful($this->problemResponse(401, [
            'title' => 'Invalid login attempt.',
        ]));
    }

    public function test_it_maps_403_to_auth_exception(): void
    {
        $this->expectException(NetSuiteAuthException::class);

        (new ResponseParser())->assertSuccessful($this->problemResponse(403, ['title' => 'Forbidden']));
    }

    public function test_it_maps_404_to_not_found_exception(): void
    {
        $this->expectException(NetSuiteNotFoundException::class);

        (new ResponseParser())->assertSuccessful($this->problemResponse(404, ['title' => 'Record not found.']));
    }

    public function test_it_maps_429_to_rate_limit_exception_with_retry_after(): void
    {
        try {
            (new ResponseParser())->assertSuccessful($this->problemResponse(
                429,
                ['title' => 'Too many requests.'],
                ['Retry-After' => '30'],
            ));
            $this->fail('Expected NetSuiteRateLimitException to be thrown.');
        } catch (NetSuiteRateLimitException $e) {
            $this->assertSame('Too many requests.', $e->getMessage());
            $this->assertSame(30, $e->retryAfterSeconds);
        }
    }

    public function test_it_maps_400_with_error_details_to_validation_exception(): void
    {
        // NetSuite's `title` is often a generic status phrase ("Bad Request") even when
        // `o:errorDetails` has the actually useful message — confirmed against a real
        // sandbox response, where `title` was just "Bad Request" for every 400. The
        // detail message must win.
        $errorDetails = [
            ['detail' => 'Invalid field name lastname1.', 'o:errorCode' => 'INVALID_FLD_VALUE', 'o:errorPath' => 'lastname1'],
        ];

        try {
            (new ResponseParser())->assertSuccessful($this->problemResponse(400, [
                'title' => 'Bad Request',
                'o:errorDetails' => $errorDetails,
            ]));
            $this->fail('Expected NetSuiteValidationException to be thrown.');
        } catch (NetSuiteValidationException $e) {
            $this->assertSame('Invalid field name lastname1.', $e->getMessage());
            $this->assertSame($errorDetails, $e->errorDetails);
        }
    }

    public function test_it_uses_the_first_error_detail_when_there_is_no_title(): void
    {
        $this->expectException(NetSuiteValidationException::class);
        $this->expectExceptionMessage('Invalid field name lastname1.');

        (new ResponseParser())->assertSuccessful($this->problemResponse(400, [
            'o:errorDetails' => [
                ['detail' => 'Invalid field name lastname1.'],
            ],
        ]));
    }

    public function test_it_maps_500_to_server_exception_with_status_code(): void
    {
        try {
            (new ResponseParser())->assertSuccessful($this->problemResponse(500, ['title' => 'Internal error.']));
            $this->fail('Expected NetSuiteServerException to be thrown.');
        } catch (NetSuiteServerException $e) {
            $this->assertSame('Internal error.', $e->getMessage());
            $this->assertSame(500, $e->statusCode);
        }
    }

    public function test_it_falls_back_to_a_generic_message_when_the_body_has_no_title_or_details(): void
    {
        $this->expectException(NetSuiteServerException::class);
        $this->expectExceptionMessage('NetSuite request failed with status 503.');

        (new ResponseParser())->assertSuccessful(new Response(503));
    }
}
