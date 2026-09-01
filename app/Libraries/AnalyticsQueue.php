<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Analytics\AnalyticsTransportInterface;
use App\DTO\AnalyticsFlushReport;
use JsonException;
use Throwable;

final class AnalyticsQueue
{
    private const PENDING_DIRECTORY = 'pending';
    private const FAILED_DIRECTORY = 'failed';
    private const LOCK_FILE = 'flush.lock';
    private const ENVELOPE_VERSION = 1;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxAttempts,
        private readonly AnalyticsTransportInterface $transport,
    ) {
    }

    /**
     * Enqueue an event without making a network request.
     *
     * @param array<string, mixed> $payload
     */
    public function enqueue(array $payload): bool
    {
        try {
            $pendingDirectory = $this->pendingDirectory();
            if (! $this->ensureDirectory($pendingDirectory)) {
                return false;
            }

            $contents = json_encode([
                'version'         => self::ENVELOPE_VERSION,
                'queued_at'       => gmdate(DATE_ATOM),
                'attempts'        => 0,
                'next_attempt_at' => null,
                'payload'         => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;

            $temporary = tempnam($pendingDirectory, '.event-');
            if ($temporary === false) {
                return false;
            }

            $written = file_put_contents($temporary, $contents, LOCK_EX);
            if ($written !== strlen($contents)) {
                @unlink($temporary);
                return false;
            }

            @chmod($temporary, 0640);
            $filename = gmdate('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.json';
            $target = $pendingDirectory . DIRECTORY_SEPARATOR . $filename;

            if (! @rename($temporary, $target)) {
                @unlink($temporary);
                return false;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function flush(int $limit): AnalyticsFlushReport
    {
        $limit = max(1, min(500, $limit));
        if (! $this->ensureDirectory($this->directory) || ! $this->ensureDirectory($this->pendingDirectory())) {
            return new AnalyticsFlushReport(0, 0, 0, 0, 0, 0);
        }

        $lock = @fopen($this->directory . DIRECTORY_SEPARATOR . self::LOCK_FILE, 'c');
        if (! is_resource($lock)) {
            return new AnalyticsFlushReport(0, 0, 0, 0, 0, $this->pendingCount());
        }

        if (! @flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return AnalyticsFlushReport::locked();
        }

        try {
            return $this->flushLocked($limit);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function flushLocked(int $limit): AnalyticsFlushReport
    {
        $processed = 0;
        $sent = 0;
        $retrying = 0;
        $failed = 0;
        $deferred = 0;
        $now = time();

        foreach ($this->pendingFiles() as $file) {
            if ($processed >= $limit) {
                break;
            }

            $envelope = $this->readEnvelope($file);
            if ($envelope === null) {
                if ($this->quarantine($file)) {
                    $failed++;
                    $processed++;
                }
                continue;
            }

            $nextAttemptAt = $envelope['next_attempt_at'];
            if ($nextAttemptAt !== null && strtotime($nextAttemptAt) > $now) {
                $deferred++;
                continue;
            }

            $result = $this->transport->send($envelope['payload']);
            $processed++;

            if ($result->accepted) {
                if (@unlink($file)) {
                    $sent++;
                } else {
                    $retrying++;
                }
                continue;
            }

            $attempts = $envelope['attempts'] + 1;
            if (! $result->retryable || $attempts >= max(1, $this->maxAttempts)) {
                if ($this->quarantine($file)) {
                    $failed++;
                } else {
                    $retrying++;
                }
                continue;
            }

            $envelope['attempts'] = $attempts;
            $envelope['next_attempt_at'] = gmdate(
                DATE_ATOM,
                $now + min(3600, 60 * (2 ** min(5, $attempts))),
            );
            $this->writeEnvelope($file, $envelope);
            $retrying++;

            // A network/5xx failure usually affects the whole upstream. Stop
            // this run after one retry so a cron invocation cannot spend
            // minutes opening doomed connections for the rest of the queue.
            break;
        }

        return new AnalyticsFlushReport(
            processed: $processed,
            sent: $sent,
            retrying: $retrying,
            failed: $failed,
            deferred: $deferred,
            remaining: $this->pendingCount(),
        );
    }

    private function pendingDirectory(): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::PENDING_DIRECTORY;
    }

    private function failedDirectory(): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . self::FAILED_DIRECTORY;
    }

    /** @return list<string> */
    private function pendingFiles(): array
    {
        $entries = @scandir($this->pendingDirectory());
        if ($entries === false) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || ! str_ends_with($entry, '.json')) {
                continue;
            }

            $path = $this->pendingDirectory() . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function pendingCount(): int
    {
        return count($this->pendingFiles());
    }

    /**
     * @return array{
     *     version: int,
     *     queued_at: string,
     *     attempts: int,
     *     next_attempt_at: string|null,
     *     payload: array<string, mixed>
     * }|null
     */
    private function readEnvelope(string $file): ?array
    {
        $contents = @file_get_contents($file);
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)
            || (int) ($decoded['version'] ?? 0) !== self::ENVELOPE_VERSION
            || ! is_string($decoded['queued_at'] ?? null)
            || ! is_int($decoded['attempts'] ?? null)
            || ! is_array($decoded['payload'] ?? null)
        ) {
            return null;
        }

        $nextAttemptAt = $decoded['next_attempt_at'] ?? null;
        if ($nextAttemptAt !== null && ! is_string($nextAttemptAt)) {
            return null;
        }

        return [
            'version' => self::ENVELOPE_VERSION,
            'queued_at' => $decoded['queued_at'],
            'attempts' => max(0, $decoded['attempts']),
            'next_attempt_at' => $nextAttemptAt,
            'payload' => $decoded['payload'],
        ];
    }

    /** @param array{version:int,queued_at:string,attempts:int,next_attempt_at:string|null,payload:array<string,mixed>} $envelope */
    private function writeEnvelope(string $file, array $envelope): bool
    {
        try {
            $contents = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $temporary = tempnam($this->pendingDirectory(), '.retry-');
            if ($temporary === false) {
                return false;
            }

            $written = file_put_contents($temporary, $contents, LOCK_EX);
            if ($written !== strlen($contents) || ! @rename($temporary, $file)) {
                @unlink($temporary);
                return false;
            }

            @chmod($file, 0640);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function quarantine(string $file): bool
    {
        if (! $this->ensureDirectory($this->failedDirectory())) {
            return false;
        }

        $target = $this->failedDirectory() . DIRECTORY_SEPARATOR . basename($file);
        if (is_file($target)) {
            $target = $this->failedDirectory() . DIRECTORY_SEPARATOR
                . pathinfo($file, PATHINFO_FILENAME) . '-' . bin2hex(random_bytes(4)) . '.json';
        }

        return @rename($file, $target);
    }

    private function ensureDirectory(string $directory): bool
    {
        return is_dir($directory)
            || (@mkdir($directory, 0750, true) && is_dir($directory));
    }
}
