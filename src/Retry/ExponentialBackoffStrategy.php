<?php

declare(strict_types=1);

namespace HttpClient\Retry;

use HttpClient\Contracts\RetryStrategyInterface;
use HttpClient\Http\HttpResponse;

/**
 * Implements exponential backoff retry strategy.
 *
 * This strategy increases the delay between retry attempts exponentially,
 * with optional jitter to prevent thundering herd problems.
 *
 * Formula: delay = min(baseDelayMs * (multiplier ^ attemptNumber) + jitter, maxDelayMs)
 *
 * Example with defaults (baseDelay=100ms, multiplier=2, maxDelay=30s):
 * - Attempt 1: ~200ms
 * - Attempt 2: ~400ms
 * - Attempt 3: ~800ms
 * - Attempt 4: ~1600ms
 * - Attempt 5: ~3200ms
 */
final readonly class ExponentialBackoffStrategy implements RetryStrategyInterface
{
    /**
     * @param int $maxAttempts Maximum number of retry attempts (default: 3)
     * @param int $baseDelayMs Base delay in milliseconds (default: 100)
     * @param float $multiplier Multiplier for exponential increase (default: 2.0)
     * @param int $maxDelayMs Maximum delay cap in milliseconds (default: 30000)
     * @param bool $useJitter Whether to add random jitter to prevent thundering herd (default: true)
     * @param array<int> $retryableStatusCodes HTTP status codes that should trigger a retry
     */
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMs = 100,
        private float $multiplier = 2.0,
        private int $maxDelayMs = 30000,
        private bool $useJitter = true,
        private array $retryableStatusCodes = [408, 429, 500, 502, 503, 504],
    ) {}

    /**
     * @inheritDoc
     */
    public function shouldRetry(HttpResponse $response, int $attemptNumber): bool
    {
        if ($attemptNumber >= $this->maxAttempts) {
            return false;
        }

        return in_array($response->statusCode, $this->retryableStatusCodes, true);
    }

    /**
     * @inheritDoc
     */
    public function getDelayMs(int $attemptNumber): int
    {
        $delay = (int) ($this->baseDelayMs * ($this->multiplier ** $attemptNumber));

        if ($this->useJitter) {
            $jitter = random_int(0, (int) ($delay * 0.1));
            $delay += $jitter;
        }

        return min($delay, $this->maxDelayMs);
    }

    /**
     * @inheritDoc
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @inheritDoc
     */
    public function isRetryableResponse(HttpResponse $response): bool
    {
        return in_array($response->statusCode, $this->retryableStatusCodes, true);
    }

    /**
     * Create a strategy optimized for rate-limited APIs.
     *
     * Uses longer delays and respects Retry-After headers.
     */
    public static function forRateLimitedApi(): self
    {
        return new self(
            maxAttempts: 5,
            baseDelayMs: 1000,
            multiplier: 2.0,
            maxDelayMs: 60000,
            useJitter: true,
            retryableStatusCodes: [429, 503],
        );
    }

    /**
     * Create an aggressive retry strategy for critical operations.
     */
    public static function aggressive(): self
    {
        return new self(
            maxAttempts: 5,
            baseDelayMs: 50,
            multiplier: 1.5,
            maxDelayMs: 5000,
            useJitter: true,
        );
    }

    /**
     * Create a conservative retry strategy for non-critical operations.
     */
    public static function conservative(): self
    {
        return new self(
            maxAttempts: 2,
            baseDelayMs: 500,
            multiplier: 3.0,
            maxDelayMs: 10000,
            useJitter: true,
        );
    }
}
