<?php

declare(strict_types=1);

namespace HttpClient\Contracts;

use HttpClient\Http\HttpResponse;

/**
 * Interface for retry strategy implementations.
 *
 * Defines the contract for determining whether a failed request
 * should be retried and how long to wait before the next attempt.
 */
interface RetryStrategyInterface
{
    /**
     * Determine if the request should be retried based on the response.
     *
     * @param HttpResponse $response The HTTP response to evaluate
     * @param int $attemptNumber The current attempt number (1-based)
     */
    public function shouldRetry(HttpResponse $response, int $attemptNumber): bool;

    /**
     * Calculate the delay in milliseconds before the next retry attempt.
     *
     * The delay should typically be non-linear (e.g., exponential backoff)
     * to avoid overwhelming the remote service.
     *
     * @param int $attemptNumber The current attempt number (1-based)
     * @return int Delay in milliseconds
     */
    public function getDelayMs(int $attemptNumber): int;

    /**
     * Get the maximum number of retry attempts allowed.
     */
    public function getMaxAttempts(): int;

    /**
     * Check if the given response has a retryable status code.
     *
     * This method only checks the status code, not the attempt number.
     * Use shouldRetry() to determine if a retry should actually be performed.
     */
    public function isRetryableResponse(HttpResponse $response): bool;
}
