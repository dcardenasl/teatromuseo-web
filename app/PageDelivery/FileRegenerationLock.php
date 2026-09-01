<?php

declare(strict_types=1);

namespace App\PageDelivery;

/** Cross-worker lock with bounded recovery from a dead builder. */
final class FileRegenerationLock implements RegenerationLockInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maxAge = 900,
    ) {
    }

    public function acquire(string $key): ?string
    {
        if ($this->directory === '') {
            return null;
        }

        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            return null;
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.lock';
        $handle = @fopen($path, 'x');
        if ($handle === false && $this->isStale($path)) {
            @unlink($path);
            $handle = @fopen($path, 'x');
        }
        if ($handle === false) {
            return null;
        }

        try {
            $token = bin2hex(random_bytes(24));
            if (fwrite($handle, $token) === false) {
                @unlink($path);

                return null;
            }
        } catch (\Throwable) {
            @unlink($path);

            return null;
        } finally {
            fclose($handle);
        }

        return $token;
    }

    private function isStale(string $path): bool
    {
        $modifiedAt = @filemtime($path);

        return is_int($modifiedAt) && $modifiedAt < time() - max(1, $this->maxAge);
    }

    public function release(string $key, string $token): void
    {
        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.lock';
        $contents = @file_get_contents($path);
        if (is_string($contents) && hash_equals($contents, $token)) {
            @unlink($path);
        }
    }
}
