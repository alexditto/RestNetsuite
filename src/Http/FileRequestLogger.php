<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Writes each logged attempt as a pair of plain-text files ({id}.request.txt and
 * {id}.response.txt) in $directory, formatted as a raw-ish HTTP message so they're
 * readable without tooling. Never throws: a full disk or bad permissions must not
 * break a real NetSuite call, it just silently drops that log entry.
 */
final class FileRequestLogger implements RequestLogger
{
    private const DEFAULT_REDACT_HEADERS = ['Authorization'];

    private readonly \Closure $idGenerator;

    public function __construct(
        private readonly string $directory,
        private readonly array $redactHeaders = self::DEFAULT_REDACT_HEADERS,
        ?\Closure $idGenerator = null,
    ) {
        $this->idGenerator = $idGenerator ?? static fn (): string => uniqid('', true);
    }

    public function log(RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): void
    {
        try {
            $this->write($request, $response, $exception);
        } catch (\Throwable) {
            // Best-effort logging — never let a logging failure break a real request.
        }
    }

    private function write(RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            return;
        }

        $id = ($this->idGenerator)();

        @file_put_contents($this->directory . '/' . $id . '.request.txt', $this->formatRequest($request));

        @file_put_contents(
            $this->directory . '/' . $id . '.response.txt',
            $exception !== null ? $this->formatException($exception) : $this->formatResponse($response),
        );
    }

    private function formatRequest(RequestInterface $request): string
    {
        $lines = [sprintf('%s %s', $request->getMethod(), (string) $request->getUri())];

        return $this->formatMessage($request, $lines);
    }

    private function formatResponse(ResponseInterface $response): string
    {
        $lines = [sprintf(
            'HTTP/%s %d %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
        )];

        return $this->formatMessage($response, $lines);
    }

    private function formatMessage(MessageInterface $message, array $lines): string
    {
        foreach (array_keys($message->getHeaders()) as $name) {
            $lines[] = sprintf('%s: %s', $name, $this->headerValue($name, $message->getHeaderLine($name)));
        }

        $lines[] = '';
        $lines[] = $this->readBody($message->getBody());

        return implode("\n", $lines);
    }

    private function formatException(\Throwable $exception): string
    {
        return sprintf("No response received (transport failure).\n\n%s: %s", $exception::class, $exception->getMessage());
    }

    private function headerValue(string $name, string $value): string
    {
        foreach ($this->redactHeaders as $redact) {
            if (strcasecmp($redact, $name) === 0) {
                return '[REDACTED]';
            }
        }

        return $value;
    }

    private function readBody(StreamInterface $body): string
    {
        if (! $body->isSeekable()) {
            return '[stream not seekable, body omitted]';
        }

        $position = $body->tell();
        $body->rewind();
        $contents = $body->getContents();
        $body->seek($position);

        return $this->prettyPrintIfJson($contents);
    }

    private function prettyPrintIfJson(string $body): string
    {
        if ($body === '') {
            return $body;
        }

        $decoded = json_decode($body, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $body;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
