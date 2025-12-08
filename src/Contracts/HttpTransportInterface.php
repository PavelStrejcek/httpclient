<?php

declare(strict_types=1);

namespace HttpClient\Contracts;

use HttpClient\Http\HttpRequest;
use HttpClient\Http\HttpResponse;

/**
 * Interface for HTTP transport implementations.
 *
 * This abstraction allows the HTTP client to work with different
 * transport mechanisms (cURL, Guzzle, Symfony HttpClient, etc.)
 * without being coupled to any specific implementation.
 */
interface HttpTransportInterface
{
    /**
     * Send an HTTP request and return the response.
     *
     * @param HttpRequest $request The HTTP request to send
     * @return HttpResponse The HTTP response
     * @throws \HttpClient\Exception\HttpTransportException If the request cannot be sent
     */
    public function send(HttpRequest $request): HttpResponse;
}
