<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Http;

use Ditto\NetSuiteClient\Http\FileRequestLogger;
use Ditto\NetSuiteClient\Tests\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

final class FileRequestLoggerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/netsuite-client-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            foreach (glob($this->directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    private function logger(array $redactHeaders = ['Authorization']): FileRequestLogger
    {
        return new FileRequestLogger($this->directory, $redactHeaders, idGenerator: fn (): string => 'fixed-id');
    }

    public function test_it_writes_a_request_and_response_file_as_a_correlated_pair(): void
    {
        $request = new Request('PATCH', 'https://1234567-sb1.suitetalk.api.netsuite.com/record/v1/contact/1', [
            'Content-Type' => 'application/json',
        ], '{"custentity_email_subscribed":false}');
        $response = new Response(204, ['Content-Type' => 'application/json']);

        $this->logger()->log($request, $response, null);

        $requestFile = $this->directory . '/fixed-id.request.txt';
        $responseFile = $this->directory . '/fixed-id.response.txt';

        $this->assertFileExists($requestFile);
        $this->assertFileExists($responseFile);

        $requestContents = file_get_contents($requestFile);
        $this->assertStringContainsString('PATCH https://1234567-sb1.suitetalk.api.netsuite.com/record/v1/contact/1', $requestContents);
        $this->assertStringContainsString('Content-Type: application/json', $requestContents);
        $this->assertStringContainsString('"custentity_email_subscribed": false', $requestContents);

        $responseContents = file_get_contents($responseFile);
        $this->assertStringContainsString('HTTP/1.1 204 No Content', $responseContents);
    }

    public function test_it_redacts_configured_headers(): void
    {
        $request = (new Request('GET', 'https://example.com/record/v1/customer/1'))
            ->withHeader('Authorization', 'Bearer super-secret-token');

        $this->logger()->log($request, new Response(200), null);

        $contents = file_get_contents($this->directory . '/fixed-id.request.txt');

        $this->assertStringNotContainsString('super-secret-token', $contents);
        $this->assertStringContainsString('Authorization: [REDACTED]', $contents);
    }

    public function test_it_pretty_prints_json_bodies(): void
    {
        $request = new Request('POST', 'https://example.com/record/v1/customer', [], '{"companyName":"Acme Co"}');

        $this->logger()->log($request, new Response(204), null);

        $contents = file_get_contents($this->directory . '/fixed-id.request.txt');

        $this->assertStringContainsString("{\n    \"companyName\": \"Acme Co\"\n}", $contents);
    }

    public function test_it_records_a_transport_failure_note_when_there_is_no_response(): void
    {
        $request = new Request('GET', 'https://example.com/record/v1/customer/1');
        $exception = new \RuntimeException('connection reset');

        $this->logger()->log($request, null, $exception);

        $contents = file_get_contents($this->directory . '/fixed-id.response.txt');

        $this->assertStringContainsString('No response received', $contents);
        $this->assertStringContainsString('RuntimeException: connection reset', $contents);
    }

    public function test_it_creates_the_log_directory_if_it_does_not_exist(): void
    {
        $this->assertDirectoryDoesNotExist($this->directory);

        $this->logger()->log(new Request('GET', 'https://example.com'), new Response(200), null);

        $this->assertDirectoryExists($this->directory);
    }

    public function test_it_does_not_throw_when_the_directory_cannot_be_created(): void
    {
        // A file in place of the target directory makes mkdir() fail.
        file_put_contents($this->directory, 'not a directory');

        $this->logger()->log(new Request('GET', 'https://example.com'), new Response(200), null);

        $this->assertFileDoesNotExist($this->directory . '/fixed-id.request.txt');

        unlink($this->directory);
    }
}
