<?php

namespace App\Services;

/**
 * LoggerService
 *
 * Simple logging service for PM2 workers and services
 * Logs to storage/logs/ with service-specific prefixes
 */
class LoggerService
{
    private string $serviceName;
    private string $logPath;

    /**
     * @param string $serviceName Name of the service for log identification
     */
    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
        $this->logPath = $this->getLogPath();

        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!file_exists($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Get the log file path
     *
     * @return string
     */
    private function getLogPath(): string
    {
        $root = PathDetectionService::detectProjectRoot();
        return $root . '/storage/logs/' . $this->serviceName . '.log';
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Write a log entry
     *
     * @param string $level Log level (INFO, WARNING, ERROR, DEBUG)
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[{$timestamp}] [{$level}] [{$this->serviceName}] {$message}{$contextStr}\n";

        // Append to log file
        @file_put_contents($this->logPath, $logLine, FILE_APPEND | LOCK_EX);

        // Output to stderr for PM2 logs (keeps stdout clean for JSON responses)
        fwrite(STDERR, $logLine);
    }

    /**
     * Get the current log file path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->logPath;
    }

    /**
     * Clear the log file
     *
     * @return bool
     */
    public function clear(): bool
    {
        return @file_put_contents($this->logPath, '') !== false;
    }

    /**
     * Get recent log entries
     *
     * @param int $lines Number of lines to retrieve
     * @return array
     */
    public function getRecentLogs(int $lines = 100): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $content = file_get_contents($this->logPath);
        $allLines = explode("\n", trim($content));

        return array_slice($allLines, -$lines);
    }
}
