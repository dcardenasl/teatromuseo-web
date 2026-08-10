<?php

declare(strict_types=1);

namespace App\PageDelivery;

use DateTimeImmutable;

/**
 * Read-only adapter for the versioned snapshot format.
 *
 * CACHE-02 owns snapshot writes and the active pointer. This adapter only
 * accepts a complete JSON file named by a hash, so a partially written file is
 * never treated as a page. A shared filesystem must be verified before enabling
 * it for public traffic.
 */
final class FileSnapshotStore implements SnapshotStoreInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function read(string $key): ?PageSnapshot
    {
        if ($this->directory === '') {
            return null;
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '' || strlen($contents) > 5 * 1024 * 1024) {
            return null;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload) || (string) ($payload['key'] ?? '') !== $key) {
            return null;
        }

        $envelope = is_array($payload['envelope'] ?? null) ? $payload['envelope'] : null;
        $generatedAt = $this->date($payload['generated_at'] ?? null);
        $expiresAt = $this->date($payload['expires_at'] ?? null);
        if ($envelope === null || $generatedAt === null || $expiresAt === null) {
            return null;
        }

        return new PageSnapshot(
            key: $key,
            envelope: $envelope,
            generatedAt: $generatedAt,
            expiresAt: $expiresAt,
            revision: is_string($payload['revision'] ?? null) ? $payload['revision'] : null,
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
