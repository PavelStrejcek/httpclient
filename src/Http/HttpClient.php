<?php

declare(strict_types=1);

namespace HttpClient\Http;

use HttpClient\Contracts\HttpTransportInterface;
use HttpClient\Contracts\LoggerInterface;
use HttpClient\Contracts\RetryStrategyInterface;
use HttpClient\Exception\HttpClientException;
use HttpClient\Exception\HttpTransportException;
use HttpClient\Exception\MaxRetriesExceededException;
use HttpClient\Logger\NullLogger;
use HttpClient\Retry\ExponentialBackoffStrategy;

/**
 * HTTP client with retry logic and error logging.
 *
 * This client wraps an HTTP transport layer and adds:
 * - Automatic retry with configurable strategy (exponential backoff by default)
 * - Comprehensive error logging
 * - Request/response lifecycle hooks
 *
 * The client follows the Single Responsibility Principle by delegating
 * actual HTTP communication to the transport layer while focusing on
 * retry logic and error handling.
 *
 * @example
 * ```php
 * $client = new HttpClient(
 *     transport: new CurlTransport(),
 *     logger: new FileLogger('/var/log/http-client.log'),
 *     retryStrategy: new ExponentialBackoffStrategy(maxAttempts: 3),
 * );
 *
 * $response = $client->post('https://api.example.com/users', [
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com',
 * ]);
 * ```
 */
final class HttpClient
{
    private readonly LoggerInterface $logger;
    private readonly RetryStrategyInterface $retryStrategy;

    /**
     * @param HttpTransportInterface      $transport      The HTTP transport implementation
     * @param null|LoggerInterface        $logger         Logger for error and debug messages
     * @param null|RetryStrategyInterface $retryStrategy  Strategy for retry logic
     * @param string                      $baseUrl        Optional base URL to prepend to all requests
     * @param array<string, string>       $defaultHeaders Default headers for all requests
     */
    public function __construct(
        private readonly HttpTransportInterface $transport,
        ?LoggerInterface $logger = null,
        ?RetryStrategyInterface $retryStrategy = null,
        private readonly string $baseUrl = '',
        private readonly array $defaultHeaders = [],
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->retryStrategy = $retryStrategy ?? new ExponentialBackoffStrategy();
    }

    /**
     * Send a GET request to the specified endpoint.
     *
     * @param string                $endpoint The API endpoint (will be appended to base URL if set)
     * @param array<string, string> $headers  Additional headers for this request
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function get(string $endpoint, array $headers = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint);
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $request = HttpRequest::get($url, $mergedHeaders);

        return $this->sendWithRetry($request);
    }

    /**
     * Send a POST request to the specified endpoint.
     *
     * @param string                $endpoint The API endpoint (will be appended to base URL if set)
     * @param array<string, mixed>  $body     The request body as an associative array
     * @param array<string, string> $headers  Additional headers for this request
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function post(string $endpoint, array $body = [], array $headers = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint);
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $request = HttpRequest::post($url, $body, $mergedHeaders);

        return $this->sendWithRetry($request);
    }

    /**
     * Send a PUT request to the specified endpoint.
     *
     * @param string                $endpoint The API endpoint (will be appended to base URL if set)
     * @param array<string, mixed>  $body     The request body as an associative array
     * @param array<string, string> $headers  Additional headers for this request
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function put(string $endpoint, array $body = [], array $headers = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint);
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $request = HttpRequest::put($url, $body, $mergedHeaders);

        return $this->sendWithRetry($request);
    }

    /**
     * Send a PATCH request to the specified endpoint.
     *
     * @param string                $endpoint The API endpoint (will be appended to base URL if set)
     * @param array<string, mixed>  $body     The request body as an associative array
     * @param array<string, string> $headers  Additional headers for this request
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function patch(string $endpoint, array $body = [], array $headers = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint);
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $request = HttpRequest::patch($url, $body, $mergedHeaders);

        return $this->sendWithRetry($request);
    }

    /**
     * Send a DELETE request to the specified endpoint.
     *
     * @param string                $endpoint The API endpoint (will be appended to base URL if set)
     * @param array<string, string> $headers  Additional headers for this request
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function delete(string $endpoint, array $headers = []): HttpResponse
    {
        $url = $this->buildUrl($endpoint);
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $request = HttpRequest::delete($url, $mergedHeaders);

        return $this->sendWithRetry($request);
    }

    /**
     * Send a request with the configured retry strategy.
     *
     * @param HttpRequest $request The request to send
     *
     * @return HttpResponse The successful response
     *
     * @throws MaxRetriesExceededException If all retry attempts fail
     * @throws HttpClientException         If a non-retryable error occurs
     */
    public function send(HttpRequest $request): HttpResponse
    {
        return $this->sendWithRetry($request);
    }

    /**
     * Execute the request with retry logic.
     *
     * @throws MaxRetriesExceededException
     * @throws HttpClientException
     */
    private function sendWithRetry(HttpRequest $request): HttpResponse
    {
        $lastResponse = null;
        $lastException = null;
        $maxAttempts = $this->retryStrategy->getMaxAttempts();

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $this->logAttempt($request, $attempt, $maxAttempts);

                $response = $this->transport->send($request);
                $lastResponse = $response;

                if ($response->isSuccessful()) {
                    $this->logSuccess($request, $response, $attempt);

                    return $response;
                }

                // Check if this is a non-retryable error (e.g., 4xx client errors)
                if (!$this->retryStrategy->isRetryableResponse($response)) {
                    $this->logNonRetryableError($request, $response);

                    throw HttpClientException::fromResponse(
                        $response,
                        \sprintf('Non-retryable error on attempt %d', $attempt),
                    );
                }

                $this->logFailedAttempt($request, $response, $attempt);

                if ($attempt < $maxAttempts) {
                    $this->waitBeforeRetry($attempt);
                }
            } catch (HttpTransportException $e) {
                $lastException = $e;
                $this->logTransportError($request, $e, $attempt);

                if ($attempt < $maxAttempts) {
                    $this->waitBeforeRetry($attempt);
                }
            }
        }

        $this->logMaxRetriesExceeded($request, $lastResponse, $maxAttempts);

        throw new MaxRetriesExceededException(
            attempts: $maxAttempts,
            lastResponse: $lastResponse,
            previous: $lastException,
        );
    }

    /**
     * Build the full URL from base URL and endpoint.
     */
    private function buildUrl(string $endpoint): string
    {
        if ('' === $this->baseUrl) {
            return $endpoint;
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Wait before the next retry attempt.
     */
    private function waitBeforeRetry(int $attempt): void
    {
        $delayMs = $this->retryStrategy->getDelayMs($attempt);

        $this->logger->debug('Waiting before retry', [
            'attempt' => $attempt,
            'delay_ms' => $delayMs,
        ]);

        usleep($delayMs * 1000);
    }

    /**
     * Log the start of a request attempt.
     */
    private function logAttempt(HttpRequest $request, int $attempt, int $maxAttempts): void
    {
        $this->logger->debug('Sending HTTP request', [
            'method' => $request->method,
            'url' => $request->url,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
        ]);
    }

    /**
     * Log a successful response.
     */
    private function logSuccess(HttpRequest $request, HttpResponse $response, int $attempt): void
    {
        $this->logger->info('HTTP request successful', [
            'method' => $request->method,
            'url' => $request->url,
            'status_code' => $response->statusCode,
            'attempt' => $attempt,
        ]);
    }

    /**
     * Log a failed attempt that will be retried.
     */
    private function logFailedAttempt(HttpRequest $request, HttpResponse $response, int $attempt): void
    {
        $this->logger->warning('HTTP request failed, will retry', [
            'method' => $request->method,
            'url' => $request->url,
            'status_code' => $response->statusCode,
            'reason' => $response->getReasonPhrase(),
            'attempt' => $attempt,
            'response_body' => mb_substr($response->body, 0, 500),
        ]);
    }

    /**
     * Log a non-retryable error.
     */
    private function logNonRetryableError(HttpRequest $request, HttpResponse $response): void
    {
        $this->logger->error('HTTP request failed with non-retryable error', [
            'method' => $request->method,
            'url' => $request->url,
            'status_code' => $response->statusCode,
            'reason' => $response->getReasonPhrase(),
            'response_body' => mb_substr($response->body, 0, 1000),
        ]);
    }

    /**
     * Log a transport-level error.
     */
    private function logTransportError(HttpRequest $request, HttpTransportException $e, int $attempt): void
    {
        $this->logger->error('HTTP transport error', [
            'method' => $request->method,
            'url' => $request->url,
            'error' => $e->getMessage(),
            'attempt' => $attempt,
        ]);
    }

    /**
     * Log when maximum retries are exceeded.
     */
    private function logMaxRetriesExceeded(HttpRequest $request, ?HttpResponse $lastResponse, int $maxAttempts): void
    {
        $this->logger->error('Maximum retry attempts exceeded', [
            'method' => $request->method,
            'url' => $request->url,
            'max_attempts' => $maxAttempts,
            'last_status_code' => $lastResponse?->statusCode,
            'last_reason' => $lastResponse?->getReasonPhrase(),
        ]);
    }
}
