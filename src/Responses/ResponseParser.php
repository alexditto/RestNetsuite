<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Responses;

use Ditto\NetSuiteClient\Exceptions\NetSuiteAuthException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteNotFoundException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteRateLimitException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteServerException;
use Ditto\NetSuiteClient\Exceptions\NetSuiteValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns a raw PSR-18 response into a NetSuiteRecord/NetSuiteCollection, or throws the
 * appropriate typed exception. NetSuite's error responses follow RFC 7807 Problem
 * Details with an `o:errorDetails` extension array of {detail, o:errorCode, o:errorPath}.
 */
final class ResponseParser
{
    public function parseRecord(ResponseInterface $response, string $recordType): NetSuiteRecord
    {
        $this->assertSuccessful($response);

        $payload = $this->decodeBody($response);
        $links = is_array($payload['links'] ?? null) ? $payload['links'] : [];
        unset($payload['links']);

        $id = isset($payload['id']) ? (string) $payload['id'] : null;

        return new NetSuiteRecord($recordType, $id, $payload, $links);
    }

    public function parseCollection(ResponseInterface $response): NetSuiteCollection
    {
        $this->assertSuccessful($response);

        $payload = $this->decodeBody($response);

        return new NetSuiteCollection(
            items: is_array($payload['items'] ?? null) ? $payload['items'] : [],
            count: (int) ($payload['count'] ?? 0),
            hasMore: (bool) ($payload['hasMore'] ?? false),
            offset: (int) ($payload['offset'] ?? 0),
            totalResults: (int) ($payload['totalResults'] ?? 0),
            links: is_array($payload['links'] ?? null) ? $payload['links'] : [],
        );
    }

    public function assertSuccessful(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        if ($status < 400) {
            return;
        }

        $payload = $this->decodeBody($response);
        $errorDetails = is_array($payload['o:errorDetails'] ?? null) ? $payload['o:errorDetails'] : [];

        // NetSuite's `title` is often just a generic RFC 9110 status phrase (e.g.
        // "Bad Request"); the actually useful message lives in `o:errorDetails[0].detail`.
        $message = $errorDetails[0]['detail']
            ?? $payload['title']
            ?? "NetSuite request failed with status {$status}.";

        match (true) {
            $status === 401 || $status === 403 => throw new NetSuiteAuthException($message),
            $status === 404 => throw new NetSuiteNotFoundException($message),
            $status === 429 => throw new NetSuiteRateLimitException($message, $this->retryAfterSeconds($response)),
            $status < 500 => throw new NetSuiteValidationException($message, $errorDetails),
            default => throw new NetSuiteServerException($message, $status),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        $payload = json_decode($body, true);

        return is_array($payload) ? $payload : [];
    }

    private function retryAfterSeconds(ResponseInterface $response): ?int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if ($retryAfter === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        $timestamp = strtotime($retryAfter);

        return $timestamp !== false ? max(0, $timestamp - time()) : null;
    }
}
