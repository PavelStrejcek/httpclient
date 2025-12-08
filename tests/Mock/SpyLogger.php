<?php

declare(strict_types=1);

namespace HttpClient\Tests\Mock;

use HttpClient\Contracts\LoggerInterface;

/**
 * Spy logger for testing purposes.
 *
 * Records all log messages for later inspection in tests.
 */
final class SpyLogger implements LoggerInterface
{
    /** @var array<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /**
     * @inheritDoc
     */
    public function error(string $message, array $context = []): void
    {
        $this->record('error', $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning(string $message, array $context = []): void
    {
        $this->record('warning', $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function info(string $message, array $context = []): void
    {
        $this->record('info', $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug(string $message, array $context = []): void
    {
        $this->record('debug', $message, $context);
    }

    /**
     * Get all recorded log entries.
     *
     * @return array<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Get log entries filtered by level.
     *
     * @return array<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getLogsByLevel(string $level): array
    {
        return array_values(
            array_filter($this->logs, fn(array $log) => $log['level'] === $level),
        );
    }

    /**
     * Check if any log message contains the given string.
     */
    public function hasLogContaining(string $needle, ?string $level = null): bool
    {
        $logs = $level !== null ? $this->getLogsByLevel($level) : $this->logs;

        foreach ($logs as $log) {
            if (str_contains($log['message'], $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the count of log entries by level.
     */
    public function countByLevel(string $level): int
    {
        return count($this->getLogsByLevel($level));
    }

    /**
     * Clear all recorded logs.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Record a log entry.
     *
     * @param array<string, mixed> $context
     */
    private function record(string $level, string $message, array $context): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
