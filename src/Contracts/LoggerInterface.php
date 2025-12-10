<?php

declare(strict_types=1);

namespace BrainWeb\HttpClient\Contracts;

/**
 * PSR-3 inspired logger interface.
 *
 * Provides a simple logging contract that can be implemented
 * by various logging backends (file, database, external services, etc.).
 */
interface LoggerInterface
{
    /**
     * Log an error message.
     *
     * @param string               $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param string               $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log an info message.
     *
     * @param string               $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param string               $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    public function debug(string $message, array $context = []): void;
}
