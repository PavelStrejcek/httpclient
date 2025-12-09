<?php

declare(strict_types=1);

namespace HttpClient\Exception;

use HttpClient\Http\HttpResponse;

/**
 * Exception thrown when the maximum number of retry attempts is exceeded.
 *
 * This exception indicates that the HTTP client exhausted all retry
 * attempts without receiving a successful response.
 */
final class MaxRetriesExceededException extends HttpClientException
{
    /**
     * @param int               $attempts     The number of attempts made
     * @param null|HttpResponse $lastResponse The last response received
     * @param null|\Throwable   $previous     The previous exception
     */
    public function __construct(
        public readonly int $attempts,
        ?HttpResponse $lastResponse = null,
        ?\Throwable $previous = null,
    ) {
        $message = \sprintf(
            'Maximum retry attempts (%d) exceeded. Last status code: %s',
            $attempts,
            $lastResponse?->statusCode ?? 'N/A',
        );

        parent::__construct(
            message: $message,
            code: $lastResponse?->statusCode ?? 0,
            previous: $previous,
            response: $lastResponse,
        );
    }
}
