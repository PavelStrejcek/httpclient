<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Tests\Mock;

use BrainWeb\HttpClient\Contracts\HttpTransportInterface;
use BrainWeb\HttpClient\Exception\HttpTransportException;
use BrainWeb\HttpClient\Http\HttpRequest;
use BrainWeb\HttpClient\Http\HttpResponse;

/**
 * Mock HTTP transport for testing purposes.
 *
 * Allows configuring a sequence of responses that will be returned
 * for consecutive requests.
 */
final class MockTransport implements HttpTransportInterface
{
    /** @var array<HttpResponse|HttpTransportException> */
    private array $responses = [];

    /** @var array<HttpRequest> */
    private array $sentRequests = [];

    private int $callIndex = 0;

    /**
     * Queue a response to be returned on the next request.
     */
    public function queueResponse(HttpResponse $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    /**
     * Queue an exception to be thrown on the next request.
     */
    public function queueException(HttpTransportException $exception): self
    {
        $this->responses[] = $exception;

        return $this;
    }

    /**
     * Queue multiple responses at once.
     *
     * @param array<HttpResponse|HttpTransportException> $responses
     */
    public function queueResponses(array $responses): self
    {
        foreach ($responses as $response) {
            $this->responses[] = $response;
        }

        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->sentRequests[] = $request;

        if (!isset($this->responses[$this->callIndex])) {
            throw new \RuntimeException(
                \sprintf('No response queued for request #%d', $this->callIndex + 1),
            );
        }

        $response = $this->responses[$this->callIndex++];

        if ($response instanceof HttpTransportException) {
            throw $response;
        }

        return $response;
    }

    /**
     * Get all requests that were sent through this transport.
     *
     * @return array<HttpRequest>
     */
    public function getSentRequests(): array
    {
        return $this->sentRequests;
    }

    /**
     * Get the number of requests sent.
     */
    public function getRequestCount(): int
    {
        return \count($this->sentRequests);
    }

    /**
     * Get a specific request by index (0-based).
     */
    public function getRequest(int $index): ?HttpRequest
    {
        return $this->sentRequests[$index] ?? null;
    }

    /**
     * Reset the mock transport to its initial state.
     */
    public function reset(): void
    {
        $this->responses = [];
        $this->sentRequests = [];
        $this->callIndex = 0;
    }
}
