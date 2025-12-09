<?php

declare(strict_types=1);

namespace HttpClient\Exception;

use HttpClient\Http\HttpResponse;

/**
 * Base exception for HTTP client errors.
 *
 * This exception is thrown when an HTTP request fails after
 * exhausting all retry attempts or when a non-retryable error occurs.
 */
class HttpClientException extends \Exception
{
    /**
     * @param string            $message  The error message
     * @param int               $code     The error code (typically HTTP status code)
     * @param null|\Throwable   $previous The previous exception
     * @param null|HttpResponse $response The HTTP response that caused the error
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?HttpResponse $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception from an HTTP response.
     */
    public static function fromResponse(HttpResponse $response, string $message = ''): self
    {
        $defaultMessage = \sprintf(
            'HTTP request failed with status %d: %s',
            $response->statusCode,
            $response->getReasonPhrase(),
        );

        return new self(
            message: $message ?: $defaultMessage,
            code: $response->statusCode,
            response: $response,
        );
    }
}
