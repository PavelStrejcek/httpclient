<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Tests;

use BrainWeb\HttpClient\Exception\HttpClientException;
use BrainWeb\HttpClient\Exception\HttpTransportException;
use BrainWeb\HttpClient\Exception\MaxRetriesExceededException;
use BrainWeb\HttpClient\Http\HttpClient;
use BrainWeb\HttpClient\Http\HttpResponse;
use BrainWeb\HttpClient\Retry\ExponentialBackoffStrategy;
use BrainWeb\HttpClient\Tests\Mock\MockTransport;
use BrainWeb\HttpClient\Tests\Mock\SpyLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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
    public function itSendsSuccessfulGetRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(200, '{"users":[]}'));

        $client = $this->createClient();
        $response = $client->get('https://api.example.com/users');

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"users":[]}', $response->body);
        self::assertSame(1, $this->transport->getRequestCount());

        $request = $this->transport->getRequest(0);
        self::assertSame('GET', $request?->method);
    }

    #[Test]
    public function itSendsSuccessfulPostRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(200, '{"status":"ok"}'));

        $client = $this->createClient();
        $response = $client->post('https://api.example.com/users', ['name' => 'John']);

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"status":"ok"}', $response->body);
        self::assertSame(1, $this->transport->getRequestCount());
    }

    #[Test]
    public function itSendsSuccessfulPutRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(200, '{"status":"updated"}'));

        $client = $this->createClient();
        $response = $client->put('https://api.example.com/users/1', ['name' => 'Jane']);

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"status":"updated"}', $response->body);

        $request = $this->transport->getRequest(0);
        self::assertNotNull($request);
        self::assertSame('PUT', $request->method);
        self::assertSame('application/json', $request->headers['Content-Type']);
    }

    #[Test]
    public function itSendsSuccessfulPatchRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(200, '{"status":"patched"}'));

        $client = $this->createClient();
        $response = $client->patch('https://api.example.com/users/1', ['name' => 'Jane']);

        self::assertSame(200, $response->statusCode);
        self::assertSame('{"status":"patched"}', $response->body);

        $request = $this->transport->getRequest(0);
        self::assertNotNull($request);
        self::assertSame('PATCH', $request->method);
        self::assertSame('application/json', $request->headers['Content-Type']);
    }

    #[Test]
    public function itSendsSuccessfulDeleteRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(204, ''));

        $client = $this->createClient();
        $response = $client->delete('https://api.example.com/users/1');

        self::assertSame(204, $response->statusCode);

        $request = $this->transport->getRequest(0);
        self::assertSame('DELETE', $request?->method);
    }

    #[Test]
    public function itIncludesJsonContentTypeHeader(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        self::assertSame('application/json', $request?->headers['Content-Type']);
    }

    #[Test]
    public function itPrependsBaseUrlToEndpoint(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(baseUrl: 'https://api.example.com');
        $client->post('/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        self::assertSame('https://api.example.com/users', $request?->url);
    }

    #[Test]
    public function itHandlesBaseUrlWithTrailingSlash(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(baseUrl: 'https://api.example.com/');
        $client->post('/users', ['name' => 'John']);

        $request = $this->transport->getRequest(0);
        self::assertSame('https://api.example.com/users', $request?->url);
    }

    #[Test]
    public function itMergesDefaultHeadersWithRequestHeaders(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient(defaultHeaders: ['X-Api-Key' => 'secret']);
        $client->post('https://api.example.com/users', [], ['X-Request-Id' => '123']);

        $request = $this->transport->getRequest(0);
        self::assertNotNull($request);
        self::assertSame('secret', $request->headers['X-Api-Key']);
        self::assertSame('123', $request->headers['X-Request-Id']);
    }

    #[Test]
    public function itRetriesOnServerError(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500, 'Internal Server Error'))
            ->queueResponse(new HttpResponse(500, 'Internal Server Error'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'))
        ;

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        self::assertSame(200, $response->statusCode);
        self::assertSame(3, $this->transport->getRequestCount());
    }

    #[Test]
    public function itRetriesOnServiceUnavailable(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'))
        ;

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $this->transport->getRequestCount());
    }

    #[Test]
    public function itRetriesOnTooManyRequests(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(429, 'Too Many Requests'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'))
        ;

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function itRetriesOnTransportException(): void
    {
        $this->transport
            ->queueException(HttpTransportException::connectionFailed('https://api.example.com'))
            ->queueResponse(new HttpResponse(200, '{"status":"ok"}'))
        ;

        $client = $this->createClient(maxAttempts: 3);
        $response = $client->post('https://api.example.com/users', []);

        self::assertSame(200, $response->statusCode);
        self::assertSame(2, $this->transport->getRequestCount());
    }

    #[Test]
    public function itThrowsMaxRetriesExceededWhenAllAttemptsFail(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500))
        ;

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(MaxRetriesExceededException::class);
        $this->expectExceptionMessage('Maximum retry attempts (3) exceeded');

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    public function itIncludesLastResponseInMaxRetriesException(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'))
            ->queueResponse(new HttpResponse(503, 'Service Unavailable'))
        ;

        $client = $this->createClient(maxAttempts: 2);

        try {
            $client->post('https://api.example.com/users', []);
            self::fail('Expected MaxRetriesExceededException');
        } catch (MaxRetriesExceededException $e) {
            self::assertSame(503, $e->response?->statusCode);
            self::assertSame(2, $e->attempts);
        }
    }

    #[Test]
    public function itDoesNotRetryOnClientError(): void
    {
        $this->transport->queueResponse(new HttpResponse(400, 'Bad Request'));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(400);

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    public function itDoesNotRetryOnNotFound(): void
    {
        $this->transport->queueResponse(new HttpResponse(404, 'Not Found'));

        $client = $this->createClient(maxAttempts: 3);

        $this->expectException(HttpClientException::class);

        $client->post('https://api.example.com/users', []);
    }

    #[Test]
    #[DataProvider('provideItDoesNotRetryOnNonRetryableStatusCodesCases')]
    public function itDoesNotRetryOnNonRetryableStatusCodes(int $statusCode): void
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
    public static function provideItDoesNotRetryOnNonRetryableStatusCodesCases(): iterable
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
    public function itLogsSuccessfulRequest(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', []);

        self::assertTrue($this->logger->hasLogContaining('HTTP request successful', 'info'));
    }

    #[Test]
    public function itLogsFailedAttempts(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(200))
        ;

        $client = $this->createClient(maxAttempts: 3);
        $client->post('https://api.example.com/users', []);

        self::assertTrue($this->logger->hasLogContaining('HTTP request failed, will retry', 'warning'));
    }

    #[Test]
    public function itLogsNonRetryableErrors(): void
    {
        $this->transport->queueResponse(new HttpResponse(400, 'Bad Request'));

        $client = $this->createClient();

        try {
            $client->post('https://api.example.com/users', []);
        } catch (HttpClientException) {
            // Expected
        }

        self::assertTrue($this->logger->hasLogContaining('non-retryable error', 'error'));
    }

    #[Test]
    public function itLogsMaxRetriesExceeded(): void
    {
        $this->transport
            ->queueResponse(new HttpResponse(500))
            ->queueResponse(new HttpResponse(500))
        ;

        $client = $this->createClient(maxAttempts: 2);

        try {
            $client->post('https://api.example.com/users', []);
        } catch (MaxRetriesExceededException) {
            // Expected
        }

        self::assertTrue($this->logger->hasLogContaining('Maximum retry attempts exceeded', 'error'));
    }

    #[Test]
    public function itLogsTransportErrors(): void
    {
        $this->transport
            ->queueException(HttpTransportException::connectionFailed('https://api.example.com'))
            ->queueResponse(new HttpResponse(200))
        ;

        $client = $this->createClient(maxAttempts: 2);
        $client->post('https://api.example.com/users', []);

        self::assertTrue($this->logger->hasLogContaining('HTTP transport error', 'error'));
    }

    #[Test]
    public function itLogsContextWithRequestDetails(): void
    {
        $this->transport->queueResponse(new HttpResponse(200));

        $client = $this->createClient();
        $client->post('https://api.example.com/users', []);

        $infoLogs = $this->logger->getLogsByLevel('info');
        self::assertNotEmpty($infoLogs);

        $context = $infoLogs[0]['context'];
        self::assertSame('POST', $context['method']);
        self::assertSame('https://api.example.com/users', $context['url']);
        self::assertSame(200, $context['status_code']);
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
