<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Tests;

use BrainWeb\HttpClient\Http\HttpResponse;
use BrainWeb\HttpClient\Retry\ExponentialBackoffStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ExponentialBackoffStrategy::class)]
final class ExponentialBackoffStrategyTest extends TestCase
{
    #[Test]
    public function itReturnsConfiguredMaxAttempts(): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 5);

        self::assertSame(5, $strategy->getMaxAttempts());
    }

    #[Test]
    #[DataProvider('provideItShouldRetryOnRetryableStatusCodesCases')]
    public function itShouldRetryOnRetryableStatusCodes(int $statusCode): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse($statusCode);

        self::assertTrue($strategy->shouldRetry($response, 1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItShouldRetryOnRetryableStatusCodesCases(): iterable
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
    #[DataProvider('provideItShouldNotRetryOnNonRetryableStatusCodesCases')]
    public function itShouldNotRetryOnNonRetryableStatusCodes(int $statusCode): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse($statusCode);

        self::assertFalse($strategy->shouldRetry($response, 1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideItShouldNotRetryOnNonRetryableStatusCodesCases(): iterable
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
    public function itShouldNotRetryWhenMaxAttemptsReached(): void
    {
        $strategy = new ExponentialBackoffStrategy(maxAttempts: 3);
        $response = new HttpResponse(500);

        self::assertFalse($strategy->shouldRetry($response, 3));
        self::assertFalse($strategy->shouldRetry($response, 4));
    }

    #[Test]
    public function itCalculatesExponentialDelay(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 100,
            multiplier: 2.0,
            useJitter: false,
        );

        self::assertSame(200, $strategy->getDelayMs(1));  // 100 * 2^1 = 200
        self::assertSame(400, $strategy->getDelayMs(2));  // 100 * 2^2 = 400
        self::assertSame(800, $strategy->getDelayMs(3));  // 100 * 2^3 = 800
        self::assertSame(1600, $strategy->getDelayMs(4)); // 100 * 2^4 = 1600
    }

    #[Test]
    public function itRespectsMaxDelay(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 10.0,
            maxDelayMs: 5000,
            useJitter: false,
        );

        // Without cap would be 10000, but capped at 5000
        self::assertSame(5000, $strategy->getDelayMs(1));
    }

    #[Test]
    public function itAddsJitterWhenEnabled(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            baseDelayMs: 1000,
            multiplier: 2.0,
            useJitter: true,
        );

        $delays = [];
        for ($i = 0; $i < 10; ++$i) {
            $delays[] = $strategy->getDelayMs(1);
        }

        // Base delay is 2000, jitter adds 0-10% (0-200ms)
        // So all delays should be between 2000 and 2200
        foreach ($delays as $delay) {
            self::assertGreaterThanOrEqual(2000, $delay);
            self::assertLessThanOrEqual(2200, $delay);
        }

        // With 10 samples, we should see some variation (not all same value)
        $uniqueDelays = array_unique($delays);
        self::assertGreaterThan(1, \count($uniqueDelays), 'Jitter should produce varying delays');
    }

    #[Test]
    public function itCreatesRateLimitedApiStrategy(): void
    {
        $strategy = ExponentialBackoffStrategy::forRateLimitedApi();

        self::assertSame(5, $strategy->getMaxAttempts());

        // Should retry on 429
        self::assertTrue($strategy->shouldRetry(new HttpResponse(429), 1));

        // Should retry on 503
        self::assertTrue($strategy->shouldRetry(new HttpResponse(503), 1));

        // Should not retry on 500 (not in rate-limited strategy)
        self::assertFalse($strategy->shouldRetry(new HttpResponse(500), 1));
    }

    #[Test]
    public function itCreatesAggressiveStrategy(): void
    {
        $strategy = ExponentialBackoffStrategy::aggressive();

        self::assertSame(5, $strategy->getMaxAttempts());
    }

    #[Test]
    public function itCreatesConservativeStrategy(): void
    {
        $strategy = ExponentialBackoffStrategy::conservative();

        self::assertSame(2, $strategy->getMaxAttempts());
    }

    #[Test]
    public function itAllowsCustomRetryableStatusCodes(): void
    {
        $strategy = new ExponentialBackoffStrategy(
            retryableStatusCodes: [418, 451], // Custom codes
        );

        // Custom codes should be retryable
        self::assertTrue($strategy->shouldRetry(new HttpResponse(418), 1));
        self::assertTrue($strategy->shouldRetry(new HttpResponse(451), 1));

        // Default codes should not be retryable
        self::assertFalse($strategy->shouldRetry(new HttpResponse(500), 1));
        self::assertFalse($strategy->shouldRetry(new HttpResponse(503), 1));
    }
}
