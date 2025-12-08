<?php

declare(strict_types=1);

namespace HttpClient\Tests;

use HttpClient\Http\HttpResponse;
use HttpClient\Retry\ExponentialBackoffStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExponentialBackoffStrategy::class)]
final class ExponentialBackoffStrategyTest extends TestCase
{
    #[Test]
    public function it_returns_configured_max_attempts(): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 5);

        $this->assertSame(5, $strategy->getMaxAttempts());
    }

    #[Test]
    #[DataProvider('retryableStatusCodesProvider')]
    public function it_should_retry_on_retryable_status_codes(int $statusCode): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse($statusCode);

        $this->assertTrue($strategy->shouldRetry($response, 1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function retryableStatusCodesProvider(): array
    {
        return [
            'Request Timeout' => [408],
            'Too Many Requests' => [429],
            'Internal Server Error' => [500],
            'Bad Gateway' => [502],
            'Service Unavailable' => [503],
            'Gateway Timeout' => [504],
        ];
    }

    #[Test]
    #[DataProvider('nonRetryableStatusCodesProvider')]
    public function it_should_not_retry_on_non_retryable_status_codes(int $statusCode): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse($statusCode);

        $this->assertFalse($strategy->shouldRetry($response, 1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function nonRetryableStatusCodesProvider(): array
    {
        return [
            'OK' => [200],
            'Created' => [201],
            'Bad Request' => [400],
            'Unauthorized' => [401],
            'Forbidden' => [403],
            'Not Found' => [404],
        ];
    }

    #[Test]
    public function it_should_not_retry_when_max_attempts_reached(): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse(500);

        $this->assertFalse($strategy->shouldRetry($response, 3));
        $this->assertFalse($strategy->shouldRetry($response, 4));
    }

    #[Test]
    public function it_calculates_exponential_delay(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 100,
            multiplier: 2.0,
            useJitter: false,
        );

        $this->assertSame(200, $strategy->getDelayMs(1));  // 100 * 2^1 = 200
        $this->assertSame(400, $strategy->getDelayMs(2));  // 100 * 2^2 = 400
        $this->assertSame(800, $strategy->getDelayMs(3));  // 100 * 2^3 = 800
        $this->assertSame(1600, $strategy->getDelayMs(4)); // 100 * 2^4 = 1600
    }

    #[Test]
    public function it_respects_max_delay(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 10.0,
            maxDelayMs: 5000,
            useJitter: false,
        );

        // Without cap would be 10000, but capped at 5000
        $this->assertSame(5000, $strategy->getDelayMs(1));
    }

    #[Test]
    public function it_adds_jitter_when_enabled(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 2.0,
            useJitter: true,
        );

        $delays = [];
        for ($i = 0; $i < 10; $i++) {
            $delays[] = $strategy->getDelayMs(1);
        }

        // Base delay is 2000, jitter adds 0-10% (0-200ms)
        // So all delays should be between 2000 and 2200
        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(2000, $delay);
            $this->assertLessThanOrEqual(2200, $delay);
        }

        // With 10 samples, we should see some variation (not all same value)
        $uniqueDelays = array_unique($delays);
        $this->assertGreaterThan(1, count($uniqueDelays), 'Jitter should produce varying delays');
    }

    #[Test]
    public function it_creates_rate_limited_api_strategy(): void
    {
        $strategy = ExponentialBackoffStrategy::forRateLimitedApi();

        $this->assertSame(5, $strategy->getMaxAttempts());

        // Should retry on 429
        $this->assertTrue($strategy->shouldRetry(new HttpResponse(429), 1));

        // Should retry on 503
        $this->assertTrue($strategy->shouldRetry(new HttpResponse(503), 1));

        // Should not retry on 500 (not in rate-limited strategy)
        $this->assertFalse($strategy->shouldRetry(new HttpResponse(500), 1));
    }

    #[Test]
    public function it_creates_aggressive_strategy(): void
    {
        $strategy = ExponentialBackoffStrategy::aggressive();

        $this->assertSame(5, $strategy->getMaxAttempts());
    }

    #[Test]
    public function it_creates_conservative_strategy(): void
    {
        $strategy = ExponentialBackoffStrategy::conservative();

        $this->assertSame(2, $strategy->getMaxAttempts());
    }

    #[Test]
    public function it_allows_custom_retryable_status_codes(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            retryableStatusCodes: [418, 451], // Custom codes
        );

        // Custom codes should be retryable
        $this->assertTrue($strategy->shouldRetry(new HttpResponse(418), 1));
        $this->assertTrue($strategy->shouldRetry(new HttpResponse(451), 1));

        // Default codes should not be retryable
        $this->assertFalse($strategy->shouldRetry(new HttpResponse(500), 1));
        $this->assertFalse($strategy->shouldRetry(new HttpResponse(503), 1));
    }
}
