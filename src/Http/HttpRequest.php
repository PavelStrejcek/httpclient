<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Http;

/**
 * Represents an HTTP request.
 *
 * This immutable value object encapsulates all the data needed
 * to perform an HTTP request, including the URL, method, headers, and body.
 */
final readonly class HttpRequest
{
    /**
     * @param string                $url     The target URL
     * @param string                $method  The HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param array<string, mixed>  $body    The request body as an associative array
     * @param array<string, string> $headers Additional HTTP headers
     */
    public function __construct(
        public string $url,
        public string $method = 'POST',
        public array $body = [],
        public array $headers = [],
    ) {}

    /**
     * Create a new GET request.
     *
     * @param string                $url     The target URL
     * @param array<string, string> $headers Additional headers
     */
    public static function get(string $url, array $headers = []): self
    {
        return new self(
            url: $url,
            method: 'GET',
            body: [],
            headers: $headers,
        );
    }

    /**
     * Create a new POST request.
     *
     * @param string                $url     The target URL
     * @param array<string, mixed>  $body    The request body
     * @param array<string, string> $headers Additional headers
     */
    public static function post(string $url, array $body = [], array $headers = []): self
    {
        return new self(
            url: $url,
            method: 'POST',
            body: $body,
            headers: array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    /**
     * Create a new PUT request.
     *
     * @param string                $url     The target URL
     * @param array<string, mixed>  $body    The request body
     * @param array<string, string> $headers Additional headers
     */
    public static function put(string $url, array $body = [], array $headers = []): self
    {
        return new self(
            url: $url,
            method: 'PUT',
            body: $body,
            headers: array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    /**
     * Create a new PATCH request.
     *
     * @param string                $url     The target URL
     * @param array<string, mixed>  $body    The request body
     * @param array<string, string> $headers Additional headers
     */
    public static function patch(string $url, array $body = [], array $headers = []): self
    {
        return new self(
            url: $url,
            method: 'PATCH',
            body: $body,
            headers: array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    /**
     * Create a new DELETE request.
     *
     * @param string                $url     The target URL
     * @param array<string, string> $headers Additional headers
     */
    public static function delete(string $url, array $headers = []): self
    {
        return new self(
            url: $url,
            method: 'DELETE',
            body: [],
            headers: $headers,
        );
    }

    /**
     * Get the body encoded as JSON.
     */
    public function getJsonBody(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    /**
     * Create a new request with additional headers.
     *
     * @param array<string, string> $headers Headers to add
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            url: $this->url,
            method: $this->method,
            body: $this->body,
            headers: array_merge($this->headers, $headers),
        );
    }
}
