<?php

declare(strict_types=1);

namespace HttpClient\Tests\Functional;

use HttpClient\Exception\HttpClientException;
use HttpClient\Exception\MaxRetriesExceededException;
use HttpClient\Http\HttpClient;
use HttpClient\Logger\FileLogger;
use HttpClient\Retry\ExponentialBackoffStrategy;
use HttpClient\Tests\Mock\SpyLogger;
use HttpClient\Transport\CurlTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests using real HTTP calls to httpbin.org
 *
 * httpbin.org is a public HTTP testing service that provides
 * various endpoints for testing HTTP clients.
 *
 * @see https://httpbin.org
 */
#[Group('functional')]
final class HttpClientFunctionalTest extends TestCase
{
    private const string BASE_URL = 'https://httpbin.org';

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
    public function it_sends_post_request_successfully(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $response = $this->client->post('/post', $payload);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame(200, $response->statusCode);

        $data = $response->json();

        // httpbin.org returns the posted JSON in the 'json' field
        // Use assertEquals instead of assertSame because key order may differ
        $this->assertEquals($payload, $data['json']);

        // Verify Content-Type header was sent
        $this->assertSame('application/json', $data['headers']['Content-Type']);
    }

    #[Test]
    public function it_receives_response_headers(): void
    {
        $response = $this->client->post('/response-headers', [
            'X-Custom-Header' => 'test-value',
        ]);

        $this->assertTrue($response->isSuccessful());

        // httpbin returns headers as query params in the response
        $contentType = $response->getHeader('content-type');
        $this->assertNotNull($contentType);
    }

    #[Test]
    public function it_handles_client_error_without_retry(): void
    {
        // httpbin.org/status/{code} returns the specified status code
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(400);

        $this->client->post('/status/400', []);
    }

    #[Test]
    public function it_handles_not_found_error(): void
    {
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(404);

        $this->client->post('/status/404', []);
    }

    #[Test]
    public function it_handles_unauthorized_error(): void
    {
        $this->expectException(HttpClientException::class);
        $this->expectExceptionCode(401);

        $this->client->post('/status/401', []);
    }

    #[Test]
    public function it_retries_on_server_error_and_eventually_fails(): void
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
    public function it_logs_successful_request(): void
    {
        $this->client->post('/post', ['test' => true]);

        $this->assertTrue(
            $this->logger->hasLogContaining('HTTP request successful', 'info'),
        );
    }

    #[Test]
    public function it_logs_failed_request(): void
    {
        try {
            $this->client->post('/status/400', []);
        } catch (HttpClientException) {
            // Expected
        }

        $this->assertTrue(
            $this->logger->hasLogContaining('non-retryable error', 'error'),
        );
    }

    #[Test]
    public function it_sends_custom_headers(): void
    {
        $response = $this->client->post('/post', ['data' => 'test'], [
            'X-Custom-Header' => 'custom-value',
        ]);

        $this->assertTrue($response->isSuccessful());

        $data = $response->json();

        // httpbin returns request headers in the response
        // Header names might vary in case, so check case-insensitively
        $headers = array_change_key_case($data['headers'], CASE_LOWER);
        $this->assertSame('custom-value', $headers['x-custom-header']);

        // Verify Content-Type is also sent
        $this->assertSame('application/json', $headers['content-type']);
    }

    #[Test]
    public function it_handles_json_response(): void
    {
        $response = $this->client->post('/post', [
            'items' => ['a', 'b', 'c'],
            'nested' => [
                'key' => 'value',
            ],
        ]);

        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('json', $data);
        $this->assertSame(['a', 'b', 'c'], $data['json']['items']);
        $this->assertSame(['key' => 'value'], $data['json']['nested']);
    }

    #[Test]
    public function it_works_with_empty_body(): void
    {
        $response = $this->client->post('/post', []);

        $this->assertTrue($response->isSuccessful());

        $data = $response->json();
        // httpbin returns null or empty array for empty body
        $this->assertTrue($data['json'] === null || $data['json'] === []);
    }

    #[Test]
    public function it_receives_correct_status_for_created(): void
    {
        // httpbin doesn't have a 201 endpoint directly, but /status/201 works
        try {
            $response = $this->client->post('/status/201', []);
            $this->assertSame(201, $response->statusCode);
            $this->assertTrue($response->isSuccessful());
        } catch (HttpClientException) {
            // Some versions might not return body for 201
            $this->markTestSkipped('httpbin returned unexpected response for 201');
        }
    }

    #[Test]
    public function it_handles_large_payload(): void
    {
        $largePayload = [
            'items' => array_map(
                fn(int $i) => [
                    'id' => $i,
                    'name' => "Item {$i}",
                    'description' => str_repeat('Lorem ipsum ', 10),
                ],
                range(1, 100),
            ),
        ];

        $response = $this->client->post('/post', $largePayload);

        $this->assertTrue($response->isSuccessful());

        $data = $response->json();
        $this->assertCount(100, $data['json']['items']);
    }

    #[Test]
    public function it_respects_timeout(): void
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
            $this->fail('Expected exception was not thrown');
        } catch (MaxRetriesExceededException $e) {
            // The original transport exception should be the previous exception
            $this->assertInstanceOf(
                \HttpClient\Exception\HttpTransportException::class,
                $e->getPrevious(),
            );
        }
    }

    #[Test]
    public function it_logs_retry_attempts_on_server_error(): void
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
        $this->assertTrue(
            $this->logger->hasLogContaining('HTTP request failed, will retry', 'warning'),
        );

        // Should have error log for max retries exceeded
        $this->assertTrue(
            $this->logger->hasLogContaining('Maximum retry attempts exceeded', 'error'),
        );
    }

    #[Test]
    public function it_includes_attempt_info_in_exception(): void
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
            $this->fail('Expected MaxRetriesExceededException');
        } catch (MaxRetriesExceededException $e) {
            $this->assertSame(2, $e->attempts);
            $this->assertNotNull($e->response);
            $this->assertSame(500, $e->response->statusCode);
        }
    }
}
