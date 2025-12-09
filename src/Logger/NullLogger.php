<?php

declare(strict_types=1);

namespace HttpClient\Logger;

use HttpClient\Contracts\LoggerInterface;

/**
 * Null logger implementation that discards all log messages.
 *
 * Useful for testing or when logging is not required.
 * Implements the Null Object pattern.
 */
final class NullLogger implements LoggerInterface
{
    public function error(string $message, array $context = []): void
    {
        // Intentionally empty - null object pattern
    }

    public function warning(string $message, array $context = []): void
    {
        // Intentionally empty - null object pattern
    }

    public function info(string $message, array $context = []): void
    {
        // Intentionally empty - null object pattern
    }

    public function debug(string $message, array $context = []): void
    {
        // Intentionally empty - null object pattern
    }
}
