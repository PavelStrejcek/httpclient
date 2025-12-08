<?php

declare(strict_types=1);

namespace HttpClient\Exception;

/**
 * Exception thrown when the HTTP transport layer fails.
 *
 * This exception indicates a low-level transport error such as
 * network connectivity issues, DNS resolution failures, or timeouts.
 */
final class HttpTransportException extends HttpClientException
{
    /**
     * Create an exception for a connection failure.
     */
    public static function connectionFailed(string $url, ?\Throwable $previous = null): self
    {
        return new self(
            message: sprintf('Failed to connect to %s', $url),
            code: 0,
            previous: $previous,
        );
    }

    /**
     * Create an exception for a timeout.
     */
    public static function timeout(string $url, int $timeoutSeconds): self
    {
        return new self(
            message: sprintf('Request to %s timed out after %d seconds', $url, $timeoutSeconds),
            code: 0,
        );
    }

    /**
     * Create an exception for DNS resolution failure.
     */
    public static function dnsResolutionFailed(string $host): self
    {
        return new self(
            message: sprintf('Failed to resolve DNS for host: %s', $host),
            code: 0,
        );
    }
}
