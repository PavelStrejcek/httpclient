<?php

declare(strict_types=1);

namespace HttpClient\Logger;

use HttpClient\Contracts\LoggerInterface;

/**
 * Simple file-based logger implementation.
 *
 * Writes log messages to a specified file with timestamps and log levels.
 * Thread-safe through file locking.
 */
final class FileLogger implements LoggerInterface
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s.u';

    /**
     * @param string $filePath Path to the log file
     * @param string $minLevel Minimum log level to record (debug, info, warning, error)
     */
    public function __construct(
        private readonly string $filePath,
        private readonly string $minLevel = 'debug',
    ) {
        $this->ensureDirectoryExists();
    }

    /**
     * @inheritDoc
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning(string $message, array $context = []): void
    {
        if ($this->shouldLog('warning')) {
            $this->log('WARNING', $message, $context);
        }
    }

    /**
     * @inheritDoc
     */
    public function info(string $message, array $context = []): void
    {
        if ($this->shouldLog('info')) {
            $this->log('INFO', $message, $context);
        }
    }

    /**
     * @inheritDoc
     */
    public function debug(string $message, array $context = []): void
    {
        if ($this->shouldLog('debug')) {
            $this->log('DEBUG', $message, $context);
        }
    }

    /**
     * Write a log entry to the file.
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context
     */
    private function log(string $level, string $message, array $context): void
    {
        $timestamp = (new \DateTimeImmutable())->format(self::DATE_FORMAT);
        $contextString = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        $entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $this->interpolate($message, $context),
            $contextString,
        );

        $this->writeToFile($entry);
    }

    /**
     * Interpolate context values into the message placeholders.
     *
     * @param string $message The message with placeholders
     * @param array<string, mixed> $context The context values
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Write content to the log file with locking.
     */
    private function writeToFile(string $content): void
    {
        $handle = fopen($this->filePath, 'a');

        if ($handle === false) {
            return;
        }

        try {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $content);
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Ensure the log directory exists.
     */
    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Check if the given level should be logged based on minimum level.
     */
    private function shouldLog(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

        return ($levels[$level] ?? 0) >= ($levels[$this->minLevel] ?? 0);
    }
}
