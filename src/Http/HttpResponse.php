<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Http;

/**
 * Represents an HTTP response.
 *
 * This immutable value object encapsulates all the data received
 * from an HTTP response, including status code, headers, and body.
 */
final readonly class HttpResponse
{
    /**
     * HTTP status codes that are considered successful.
     */
    private const array SUCCESS_CODES = [200, 201, 202, 203, 204, 205, 206];

    /**
     * HTTP status codes that indicate the request can be retried.
     */
    private const array RETRYABLE_CODES = [408, 429, 500, 502, 503, 504];

    /**
     * @param int                   $statusCode The HTTP status code
     * @param string                $body       The raw response body
     * @param array<string, string> $headers    The response headers
     */
    public function __construct(
        public int $statusCode,
        public string $body = '',
        public array $headers = [],
    ) {}

    /**
     * Check if the response indicates success (2xx status code).
     */
    public function isSuccessful(): bool
    {
        return \in_array($this->statusCode, self::SUCCESS_CODES, true);
    }

    /**
     * Check if the response indicates an error that can be retried.
     *
     * Retryable errors include:
     * - 408 Request Timeout
     * - 429 Too Many Requests
     * - 500 Internal Server Error
     * - 502 Bad Gateway
     * - 503 Service Unavailable
     * - 504 Gateway Timeout
     */
    public function isRetryable(): bool
    {
        return \in_array($this->statusCode, self::RETRYABLE_CODES, true);
    }

    /**
     * Check if the response indicates a client error (4xx status code).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if the response indicates a server error (5xx status code).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Decode the JSON body into an associative array.
     *
     * @return array<string, mixed>
     *
     * @throws \JsonException If the body is not valid JSON
     */
    public function json(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Get a specific header value.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Get the reason phrase for the status code.
     */
    public function getReasonPhrase(): string
    {
        return match ($this->statusCode) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Unknown',
        };
    }
}
