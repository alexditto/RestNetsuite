<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Http;

use Ditto\NetSuiteClient\Http\LoggingMiddleware;
use Ditto\NetSuiteClient\Http\RequestLogger;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LoggingMiddlewareTest extends TestCase
{
    private function recordingLogger(): RequestLogger
    {
        return new class implements RequestLogger {
            public int $callCount = 0;
            public ?RequestInterface $request = null;
            public ?ResponseInterface $response = null;
            public ?\Throwable $exception = null;

            public function log(RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): void
            {
                $this->callCount++;
                $this->request = $request;
                $this->response = $response;
                $this->exception = $exception;
            }
        };
    }

    public function test_it_logs_and_returns_a_successful_response_unchanged(): void
    {
        $inner = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        };
        $logger = $this->recordingLogger();
        $middleware = new LoggingMiddleware($inner, $logger);
        $request = new Request('GET', 'https://example.com/record/customer/1');

        $response = $middleware->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $logger->callCount);
        $this->assertSame($request, $logger->request);
        $this->assertSame($response, $logger->response);
        $this->assertNull($logger->exception);
    }

    public function test_it_logs_and_rethrows_a_transport_exception_unchanged(): void
    {
        $exception = new class ('connection reset') extends \RuntimeException implements ClientExceptionInterface {
        };
        $inner = new class ($exception) implements ClientInterface {
            public function __construct(private \Throwable $exception)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };
        $logger = $this->recordingLogger();
        $middleware = new LoggingMiddleware($inner, $logger);
        $request = new Request('GET', 'https://example.com/record/customer/1');

        try {
            $middleware->sendRequest($request);
            $this->fail('Expected the transport exception to propagate.');
        } catch (\Throwable $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame(1, $logger->callCount);
        $this->assertSame($request, $logger->request);
        $this->assertNull($logger->response);
        $this->assertSame($exception, $logger->exception);
    }
}
