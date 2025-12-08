<?php

declare(strict_types=1);

namespace HttpClient\Tests;

use HttpClient\Exception\HttpClientException;
use HttpClient\Exception\MaxRetriesExceededException;
use HttpClient\Exception\HttpTransportException;
use HttpClient\Http\HttpClient;
use HttpClient\Http\HttpResponse;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Tests\Mock\MockTransport;
use HttpClient\Tests\Mock\SpyLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpClient::class)]
final class HttpClientTest extends TestCase
{
    private MockTransport $transport;
    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->transport = new MockTransport();
        $this->logger = new SpyLogger();
    }

    #[Test]
    public function it_sends_successful_post_request(): void
    {
        $this->transport->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient();
        $response = $client->post('https://api.example.com/users', ['name' => 'John']);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('{"status":"ok"}', $response->body);
        $this->assertSame(1, $this->transport->getRequestCount());
    }

    #[Test]
    public function it_includes_json_content_type_header(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        $this->assertSame('application/json', $request?->headers['Content-Type']);
    }

    #[Test]
    public function it_prepends_base_url_to_endpoint(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(baseUrl: 'https://api.example.com');
        $client->post('/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        $this->assertSame('https://api.example.com/users', $request?->url);
    }

    #[Test]
    public function it_handles_base_url_with_trailing_slash(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(baseUrl: 'https://api.example.com/');
        $client->post('/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        $this->assertSame('https://api.example.com/users', $request?->url);
    }

    #[Test]
    public function it_merges_default_headers_with_request_headers(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(defaultHeaders: ['X-Api-Key' => 'secret']);
        $client->post('https://api.example.com/users', [], ['X-Request-Id' => '123']);

        $request = $this->transport->getRequest(0);
        $this->assertSame('secret', $request?->headers['X-Api-Key']);
        $this->assertSame('123', $request?->headers['X-Request-Id']);
    }

    #[Test]
    public function it_retries_on_server_error(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500, 'Internal Server Error'))
            ->queueResponse(new HttpResponse(500, 'Internal Server Error'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(3, $this->transport->getRequestCount());
    }

    #[Test]
    public function it_retries_on_service_unavailable(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(2, $this->transport->getRequestCount());
    }

    #[Test]
    public function it_retries_on_too_many_requests(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(429, 'Too Many Requests'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function it_retries_on_transport_exception(): void
    {
        $this->transport
            ->queueException(HttpTransportException::connectionFailed('https://api.example.com'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(2, $this->transport->getRequestCount());
    }

    #[Test]
    public function it_throws_max_retries_exceeded_when_all_attempts_fail(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(MaxRetriesExceededException::class);
        $this->expectExceptionMessage('Maximum retry attempts (3) exceeded');

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    public function it_includes_last_response_in_max_retries_exception(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'))
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'));

        $client = $this->createClient(maxAttempts: 2);

        try {
            $client->post('https://api.example.com/users', []);
            $this->fail('Expected MaxRetriesExceededException');
        } catch (MaxRetriesExceededException $e) {
            $this->assertSame(503, $e->response?->statusCode);
            $this->assertSame(2, $e->attempts);
        }
    }

    #[Test]
    public function it_does_not_retry_on_client_error(): void
    {
        $this->transport->queueResponse(new HttpResponse(400, 'Bad Request'));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(400);

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    public function it_does_not_retry_on_not_found(): void
    {
        $this->transport->queueResponse(new HttpResponse(404, 'Not Found'));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(HttpClientException::class);

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    #[DataProvider('nonRetryableStatusCodesProvider')]
    public function it_does_not_retry_on_non_retryable_status_codes(int $statusCode): void
    {
        $this->transport->queueResponse(new HttpResponse($statusCode));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode($statusCode);

        $client->post('https://api.example.com/users', []);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonRetryableStatusCodesProvider(): array
    {
        return [
            'Bad Request' => [400],
            'Unauthorized' => [401],
            'Forbidden' => [403],
            'Not Found' => [404],
            'Method Not Allowed' => [405],
            'Conflict' => [409],
            'Unprocessable Entity' => [422],
        ];
    }

    #[Test]
    public function it_logs_successful_request(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', []);

        $this->assertTrue($this->logger->hasLogContaining('HTTP request successful', 'info'));
    }

    #[Test]
    public function it_logs_failed_attempts(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(200));

        $client = $this->createClient(maxAttempts: 3);
        $client->post('https://api.example.com/users', []);

        $this->assertTrue($this->logger->hasLogContaining('HTTP request failed, will retry', 'warning'));
    }

    #[Test]
    public function it_logs_non_retryable_errors(): void
    {
        $this->transport->queueResponse(new HttpResponse(400, 'Bad Request'));

        $client = $this->createClient();

        try {
            $client->post('https://api.example.com/users', []);
        } catch (HttpClientException) {
            // Expected
        }

        $this->assertTrue($this->logger->hasLogContaining('non-retryable error', 'error'));
    }

    #[Test]
    public function it_logs_max_retries_exceeded(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500));

        $client = $this->createClient(maxAttempts: 2);

        try {
            $client->post('https://api.example.com/users', []);
        } catch (MaxRetriesExceededException) {
            // Expected
        }

        $this->assertTrue($this->logger->hasLogContaining('Maximum retry attempts exceeded', 'error'));
    }

    #[Test]
    public function it_logs_transport_errors(): void
    {
        $this->transport
            ->queueException(HttpTransportException::connectionFailed('https://api.example.com'))
            ->queueResponse(new HttpResponse(200));

        $client = $this->createClient(maxAttempts: 2);
        $client->post('https://api.example.com/users', []);

        $this->assertTrue($this->logger->hasLogContaining('HTTP transport error', 'error'));
    }

    #[Test]
    public function it_logs_context_with_request_details(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', []);

        $infoLogs = $this->logger->getLogsByLevel('info');
        $this->assertNotEmpty($infoLogs);

        $context = $infoLogs[0]['context'];
        $this->assertSame('POST', $context['method']);
        $this->assertSame('https://api.example.com/users', $context['url']);
        $this->assertSame(200, $context['status_code']);
    }

    /**
     * Create an HTTP client with test dependencies.
     *
     * @param array<string, string> $defaultHeaders
     */
    private function createClient(
        int $maxAttempts = 3,
        string $baseUrl = '',
        array $defaultHeaders = [],
    ): HttpClient {
        return new HttpClient(
            transport: $this->transport,
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(
                maxAttempts: $maxAttempts,
                baseDelayMs: 1, // Minimal delay for tests
                useJitter: false,
            ),
            baseUrl: $baseUrl,
            defaultHeaders: $defaultHeaders,
        );
    }
}
