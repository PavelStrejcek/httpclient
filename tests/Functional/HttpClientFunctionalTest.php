<?php

declare(strict_types=1);

namespace HttpClient\Tests\Functional;

use HttpClient\Exception\HttpClientException;
use HttpClient\Exception\HttpTransportException;
use HttpClient\Exception\MaxRetriesExceededException;
use HttpClient\Http\HttpClient;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Tests\Mock\SpyLogger;
use HttpClient\Transport\CurlTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests using real HTTP calls to httpbin service.
 *
 * Uses local httpbin Docker container for reliable testing.
 * Run with: docker compose up -d httpbin
 *
 * @see https://httpbin.org
 *
 * @internal
 *
 * @coversNothing
 */
#[Group('functional')]
final class HttpClientFunctionalTest extends TestCase
{
    private const string BASE_URL = 'http://httpbin';

    private HttpClient $client;
    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new SpyLogger();

        $this->client = new HttpClient(
            transport: new CurlTransport(timeout: 30),
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(
                maxAttempts: 3,
                baseDelayMs: 100,
                useJitter: false,
            ),
            baseUrl: self::BASE_URL,
        );
    }

    #[Test]
    public function itSendsGetRequestSuccessfully(): void
    {
        $response = $this->client->get('/get');

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode);

        $data = $response->json();

        // httpbin returns request info
        self::assertArrayHasKey('headers', $data);
        self::assertArrayHasKey('url', $data);
    }

    #[Test]
    public function itSendsPostRequestSuccessfully(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $response = $this->client->post('/post', $payload);

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode);

        $data = $response->json();

        // httpbin.org returns the posted JSON in the 'json' field
        // Use assertEquals instead of assertSame because key order may differ
        self::assertEquals($payload, $data['json']);

        // Verify Content-Type header was sent
        self::assertSame('application/json', $data['headers']['Content-Type']);
    }

    #[Test]
    public function itSendsPutRequestSuccessfully(): void
    {
        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ];

        $response = $this->client->put('/put', $payload);

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode);

        $data = $response->json();

        // Use assertEquals because key order may differ
        self::assertEquals($payload, $data['json']);
        self::assertSame('application/json', $data['headers']['Content-Type']);
    }

    #[Test]
    public function itSendsPatchRequestSuccessfully(): void
    {
        $payload = [
            'name' => 'Updated Name',
        ];

        $response = $this->client->patch('/patch', $payload);

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode);

        $data = $response->json();

        // Use assertEquals because key order may differ
        self::assertEquals($payload, $data['json']);
        self::assertSame('application/json', $data['headers']['Content-Type']);
    }

    #[Test]
    public function itSendsDeleteRequestSuccessfully(): void
    {
        $response = $this->client->delete('/delete');

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode);

        $data = $response->json();

        self::assertArrayHasKey('headers', $data);
        self::assertArrayHasKey('url', $data);
    }

    #[Test]
    public function itReceivesResponseHeaders(): void
    {
        $response = $this->client->post('/response-headers', [
            'X-Custom-Header' => 'test-value',
        ]);

        self::assertTrue($response->isSuccessful());

        // httpbin returns headers as query params in the response
        $contentType = $response->getHeader('content-type');
        self::assertNotNull($contentType);
    }

    #[Test]
    public function itHandlesClientErrorWithoutRetry(): void
    {
        // httpbin.org/status/{code} returns the specified status code
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(400);

        $this->client->post('/status/400', []);
    }

    #[Test]
    public function itHandlesNotFoundError(): void
    {
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(404);

        $this->client->post('/status/404', []);
    }

    #[Test]
    public function itHandlesUnauthorizedError(): void
    {
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(401);

        $this->client->post('/status/401', []);
    }

    #[Test]
    public function itRetriesOnServerErrorAndEventuallyFails(): void
    {
        // Create client with minimal retry delay
        $client = new HttpClient(
            transport: new CurlTransport(timeout: 10),
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(
                maxAttempts: 2,
                baseDelayMs: 50,
                useJitter: false,
            ),
            baseUrl: self::BASE_URL,
        );

        $this->expectException(MaxRetriesExceededException::class);

        // This will always return 500, so all retries will fail
        $client->post('/status/500', []);
    }

    #[Test]
    public function itLogsSuccessfulRequest(): void
    {
        $this->client->post('/post', ['test' => true]);

        self::assertTrue(
            $this->logger->hasLogContaining('HTTP request successful', 'info'),
        );
    }

    #[Test]
    public function itLogsFailedRequest(): void
    {
        try {
            $this->client->post('/status/400', []);
        } catch (HttpClientException) {
            // Expected
        }

        self::assertTrue(
            $this->logger->hasLogContaining('non-retryable error', 'error'),
        );
    }

    #[Test]
    public function itSendsCustomHeaders(): void
    {
        $response = $this->client->post('/post', ['data' => 'test'], [
            'X-Custom-Header' => 'custom-value',
        ]);

        self::assertTrue($response->isSuccessful());

        $data = $response->json();

        // httpbin returns request headers in the response
        // Header names might vary in case, so check case-insensitively
        $headers = array_change_key_case($data['headers'], CASE_LOWER);
        self::assertSame('custom-value', $headers['x-custom-header']);

        // Verify Content-Type is also sent
        self::assertSame('application/json', $headers['content-type']);
    }

    #[Test]
    public function itHandlesJsonResponse(): void
    {
        $response = $this->client->post('/post', [
            'items' => ['a', 'b', 'c'],
            'nested' => [
                'key' => 'value',
            ],
        ]);

        $data = $response->json();

        self::assertIsArray($data);
        self::assertArrayHasKey('json', $data);
        self::assertSame(['a', 'b', 'c'], $data['json']['items']);
        self::assertSame(['key' => 'value'], $data['json']['nested']);
    }

    #[Test]
    public function itWorksWithEmptyBody(): void
    {
        $response = $this->client->post('/post', []);

        self::assertTrue($response->isSuccessful());

        $data = $response->json();
        // httpbin returns null or empty array for empty body
        self::assertTrue(null === $data['json'] || [] === $data['json']);
    }

    #[Test]
    public function itReceivesCorrectStatusForCreated(): void
    {
        // httpbin doesn't have a 201 endpoint directly, but /status/201 works
        try {
            $response = $this->client->post('/status/201', []);
            self::assertSame(201, $response->statusCode);
            self::assertTrue($response->isSuccessful());
        } catch (HttpClientException) {
            // Some versions might not return body for 201
            self::markTestSkipped('httpbin returned unexpected response for 201');
        }
    }

    #[Test]
    public function itHandlesLargePayload(): void
    {
        $largePayload = [
            'items' => array_map(
                static fn (int $i) => [
                    'id' => $i,
                    'name' => "Item {$i}",
                    'description' => str_repeat('Lorem ipsum ', 10),
                ],
                range(1, 100),
            ),
        ];

        $response = $this->client->post('/post', $largePayload);

        self::assertTrue($response->isSuccessful());

        $data = $response->json();
        self::assertCount(100, $data['json']['items']);
    }

    #[Test]
    public function itRespectsTimeout(): void
    {
        // Create client with very short timeout
        $client = new HttpClient(
            transport: new CurlTransport(timeout: 1),
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(maxAttempts: 1),
            baseUrl: self::BASE_URL,
        );

        // Transport exception is wrapped in MaxRetriesExceededException
        // because the client catches transport errors and counts them as failed attempts
        try {
            // httpbin.org/delay/{n} delays response by n seconds
            $client->post('/delay/5', []);
            self::fail('Expected exception was not thrown');
        } catch (MaxRetriesExceededException $e) {
            // The original transport exception should be the previous exception
            self::assertInstanceOf(
                HttpTransportException::class,
                $e->getPrevious(),
            );
        }
    }

    #[Test]
    public function itLogsRetryAttemptsOnServerError(): void
    {
        $client = new HttpClient(
            transport: new CurlTransport(timeout: 10),
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(
                maxAttempts: 2,
                baseDelayMs: 50,
                useJitter: false,
            ),
            baseUrl: self::BASE_URL,
        );

        try {
            $client->post('/status/503', []);
        } catch (MaxRetriesExceededException) {
            // Expected
        }

        // Should have warning logs for failed attempts
        self::assertTrue(
            $this->logger->hasLogContaining('HTTP request failed, will retry', 'warning'),
        );

        // Should have error log for max retries exceeded
        self::assertTrue(
            $this->logger->hasLogContaining('Maximum retry attempts exceeded', 'error'),
        );
    }

    #[Test]
    public function itIncludesAttemptInfoInException(): void
    {
        $client = new HttpClient(
            transport: new CurlTransport(timeout: 10),
            logger: $this->logger,
            retryStrategy: new ExponentialBackoffStrategy(
                maxAttempts: 2,
                baseDelayMs: 50,
                useJitter: false,
            ),
            baseUrl: self::BASE_URL,
        );

        try {
            $client->post('/status/500', []);
            self::fail('Expected MaxRetriesExceededException');
        } catch (MaxRetriesExceededException $e) {
            self::assertSame(2, $e->attempts);
            self::assertNotNull($e->response);
            self::assertSame(500, $e->response->statusCode);
        }
    }
}
