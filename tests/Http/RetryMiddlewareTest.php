<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Http;

use Ditto\NetSuiteClient\Http\RetryMiddleware;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RetryMiddlewareTest extends TestCase
{
    private function queuedClient(array $responses): ClientInterface
    {
        return new class ($responses) implements ClientInterface {
            public int $callCount = 0;

            public function __construct(private array $responses)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                $response = array_shift($this->responses);

                if ($response === null) {
                    throw new \RuntimeException('No more queued responses.');
                }

                return $response;
            }
        };
    }

    public function test_it_returns_immediately_on_a_non_429_response(): void
    {
        $inner = $this->queuedClient([new Response(200)]);
        $sleeps = [];
        $middleware = new RetryMiddleware($inner, sleeper: function (float $s) use (&$sleeps) {
            $sleeps[] = $s;
        });

        $response = $middleware->sendRequest(new Request('GET', 'https://example.com'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $inner->callCount);
        $this->assertSame([], $sleeps);
    }

    public function test_it_retries_on_429_with_exponential_backoff_until_success(): void
    {
        $inner = $this->queuedClient([
            new Response(429),
            new Response(429),
            new Response(200),
        ]);
        $sleeps = [];
        $middleware = new RetryMiddleware(
            $inner,
            baseDelaySeconds: 1.0,
            sleeper: function (float $s) use (&$sleeps) {
                $sleeps[] = $s;
            },
        );

        $response = $middleware->sendRequest(new Request('GET', 'https://example.com'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $inner->callCount);
        $this->assertSame([1.0, 2.0], $sleeps);
    }

    public function test_it_respects_a_numeric_retry_after_header(): void
    {
        $inner = $this->queuedClient([
            new Response(429, ['Retry-After' => '5']),
            new Response(200),
        ]);
        $sleeps = [];
        $middleware = new RetryMiddleware($inner, sleeper: function (float $s) use (&$sleeps) {
            $sleeps[] = $s;
        });

        $middleware->sendRequest(new Request('GET', 'https://example.com'));

        $this->assertSame([5.0], $sleeps);
    }

    public function test_it_caps_the_delay_at_max_delay_seconds(): void
    {
        $inner = $this->queuedClient([
            new Response(429, ['Retry-After' => '999']),
            new Response(200),
        ]);
        $sleeps = [];
        $middleware = new RetryMiddleware(
            $inner,
            maxDelaySeconds: 30.0,
            sleeper: function (float $s) use (&$sleeps) {
                $sleeps[] = $s;
            },
        );

        $middleware->sendRequest(new Request('GET', 'https://example.com'));

        $this->assertSame([30.0], $sleeps);
    }

    public function test_it_gives_up_after_max_attempts_and_returns_the_final_429(): void
    {
        $inner = $this->queuedClient([
            new Response(429),
            new Response(429),
            new Response(429),
        ]);
        $middleware = new RetryMiddleware(
            $inner,
            maxAttempts: 3,
            sleeper: function (float $s) {},
        );

        $response = $middleware->sendRequest(new Request('GET', 'https://example.com'));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(3, $inner->callCount);
    }
}
