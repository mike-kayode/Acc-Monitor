<?php
/**
 * Application Logger
 * Structured logging to files with log levels
 */

namespace NAPM\Core;

class Logger
{
    private string $logDir;
    private array $logLevels = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];
    private int $minLevel = 200; // info by default

    public function __construct(string $logDir, string $minLevel = 'info')
    {
        $this->logDir = rtrim($logDir, '/') . '/';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        $this->minLevel = $this->logLevels[$minLevel] ?? 200;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!isset($this->logLevels[$level]) || $this->logLevels[$level] < $this->minLevel) {
            return;
        }

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $date = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $logFile = $this->logDir . $date . '.log';

        $entry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}