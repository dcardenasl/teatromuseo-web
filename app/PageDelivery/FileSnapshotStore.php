<?php

declare(strict_types=1);

namespace App\PageDelivery;

use DateTimeImmutable;
use JsonException;

/**
 * Shared-filesystem snapshot backend.
 *
 * A snapshot artifact is written in the same directory as its final object
 * and then renamed. The active pointer is published separately, also by
 * rename. Readers follow only the pointer and therefore never observe a
 * partially written artifact. The configured directory must be shared by all
 * workers before this backend is enabled for public traffic.
 */
final class FileSnapshotStore implements SnapshotPublisherInterface
{
    private const SCHEMA_VERSION = 2;
    private const MAX_POINTER_BYTES = 32_768;
    private const MAX_MARKER_BYTES = 16_384;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes = 5_242_880,
        private readonly int $retention = 3,
        private readonly string $compression = 'gzip',
    ) {
    }

    public function read(string $key): ?PageSnapshot
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $pointer = $this->readJson($this->pointerPath($key), self::MAX_POINTER_BYTES);
        if ($pointer === null
            || (int) ($pointer['schema_version'] ?? 0) !== self::SCHEMA_VERSION
            || (string) ($pointer['key'] ?? '') !== $key
        ) {
            return null;
        }

        $revision = (string) ($pointer['revision'] ?? '');
        $encoding = (string) ($pointer['encoding'] ?? 'identity');
        if (! preg_match('/\A[a-f0-9]{64}\z/', $revision) || ! in_array($encoding, ['identity', 'gzip'], true)) {
            return null;
        }

        $artifactPath = $this->artifactPath($key, $revision, $encoding);
        $raw = @file_get_contents($artifactPath);
        if (! is_string($raw) || $raw === '' || strlen($raw) > $this->maxBytes) {
            return null;
        }
        if ($encoding === 'gzip') {
            $decoded = function_exists('gzdecode') ? @gzdecode($raw) : false;
            if (! is_string($decoded)) {
                return null;
            }
            $raw = $decoded;
        }
        if (strlen($raw) > $this->maxBytes) {
            return null;
        }

        $payload = $this->decode($raw);
        if ($payload === null
            || (int) ($payload['schema_version'] ?? 0) !== self::SCHEMA_VERSION
            || (string) ($payload['key'] ?? '') !== $key
            || (string) ($payload['revision'] ?? '') !== $revision
        ) {
            return null;
        }

        $envelope = is_array($payload['envelope'] ?? null) ? $payload['envelope'] : null;
        $generatedAt = $this->date($payload['generated_at'] ?? null);
        $expiresAt = $this->date($payload['expires_at'] ?? null);
        if ($envelope === null || $generatedAt === null || $expiresAt === null) {
            return null;
        }

        $scopes = $this->strings($payload['scopes'] ?? []);
        $invalidatedAt = $this->invalidationDate($key, $generatedAt, $scopes);

        return new PageSnapshot(
            key: $key,
            envelope: $envelope,
            generatedAt: $generatedAt,
            expiresAt: $expiresAt,
            revision: $revision,
            etag: is_string($pointer['etag'] ?? null) ? $pointer['etag'] : null,
            scopes: $scopes,
            invalidatedAt: $invalidatedAt,
        );
    }

    public function publish(PageSnapshot $snapshot): bool
    {
        if (! $this->ensureDirectories() || $snapshot->key === '' || $snapshot->envelope === []) {
            return false;
        }

        $revision = $snapshot->revision ?: hash(
            'sha256',
            'page-snapshot-v2|' . $snapshot->key . '|' . $this->encodeJson($snapshot->envelope),
        );
        if (! preg_match('/\A[a-f0-9]{64}\z/', $revision)) {
            return false;
        }

        $document = [
            'schema_version' => self::SCHEMA_VERSION,
            'key' => $snapshot->key,
            'revision' => $revision,
            'generated_at' => $snapshot->generatedAt->format(DATE_ATOM),
            'expires_at' => $snapshot->expiresAt->format(DATE_ATOM),
            'scopes' => array_values(array_unique($snapshot->scopes)),
            'envelope' => $snapshot->envelope,
        ];

        try {
            $json = $this->encodeJson($document);
        } catch (JsonException) {
            return false;
        }

        $encoding = $this->compression === 'gzip' && function_exists('gzencode') ? 'gzip' : 'identity';
        $contents = $encoding === 'gzip' ? @gzencode($json, 6, FORCE_GZIP) : $json;
        if (! is_string($contents) || $contents === '' || strlen($contents) > $this->maxBytes) {
            return false;
        }

        $etag = '"' . hash('sha256', $contents) . '"';
        $artifactPath = $this->artifactPath($snapshot->key, $revision, $encoding);
        if (! $this->atomicWrite($artifactPath, $contents)) {
            return false;
        }

        $pointer = [
            'schema_version' => self::SCHEMA_VERSION,
            'key' => $snapshot->key,
            'revision' => $revision,
            'encoding' => $encoding,
            'etag' => $etag,
            'bytes' => strlen($contents),
            'scopes' => array_values(array_unique($snapshot->scopes)),
            'locale' => is_string($snapshot->envelope['meta']['locale'] ?? null) ? $snapshot->envelope['meta']['locale'] : null,
            'route' => is_string($snapshot->envelope['meta']['route'] ?? null) ? $snapshot->envelope['meta']['route'] : null,
            'published_at' => gmdate(DATE_ATOM),
        ];
        try {
            $pointerJson = $this->encodeJson($pointer);
        } catch (JsonException) {
            return false;
        }
        if (! $this->atomicWrite($this->pointerPath($snapshot->key), $pointerJson)) {
            return false;
        }

        $this->clearObsoleteInvalidation($snapshot->key, $snapshot->generatedAt);
        $this->prune($snapshot->key, $revision);

        return true;
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $locales
     * @param list<string> $routes
     */
    public function invalidateScopes(array $scopes, array $locales = [], array $routes = []): int
    {
        $scopes = array_values(array_unique($this->strings($scopes)));
        $locales = array_values(array_unique($this->strings($locales)));
        $routes = array_values(array_unique($this->strings($routes)));
        if ($scopes === [] || ! $this->ensureDirectories()) {
            return 0;
        }

        $affected = 0;
        foreach ($this->pointerFiles() as $pointerPath) {
            $pointer = $this->readJson($pointerPath, self::MAX_POINTER_BYTES);
            if ($pointer === null) {
                continue;
            }

            $snapshotScopes = $this->strings($pointer['scopes'] ?? []);
            // Older or manually created pointers may omit scopes. Treat them
            // as affected by a content invalidation rather than risk serving
            // an unknown variant forever.
            if ($snapshotScopes !== [] && array_intersect($scopes, $snapshotScopes) === []) {
                continue;
            }
            if ($locales !== [] && ! in_array((string) ($pointer['locale'] ?? ''), $locales, true)) {
                continue;
            }
            if ($routes !== [] && ! in_array((string) ($pointer['route'] ?? ''), $routes, true)) {
                continue;
            }

            $key = is_string($pointer['key'] ?? null) ? $pointer['key'] : '';
            if ($key === '') {
                continue;
            }

            $marker = [
                'schema_version' => self::SCHEMA_VERSION,
                'key' => $key,
                'scopes' => $scopes,
                'locales' => $locales,
                'routes' => $routes,
                'invalidated_at' => gmdate(DATE_ATOM),
            ];
            if ($this->atomicWrite($this->invalidationPath($key), $this->encodeJson($marker))) {
                $affected++;
            }
        }

        return $affected;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $enabled = $this->ensureDirectories();

        return [
            'enabled' => $enabled,
            'backend' => 'file',
            'shared' => $enabled,
            'directory' => $this->directory,
            'max_bytes' => $this->maxBytes,
            'retention' => $this->retention,
            'compression' => $this->compression,
        ];
    }

    private function isConfigured(): bool
    {
        return $this->directory !== '' && is_dir($this->directory) && is_readable($this->directory);
    }

    private function ensureDirectories(): bool
    {
        if ($this->directory === '') {
            return false;
        }

        foreach ([$this->directory, $this->directory . '/objects', $this->directory . '/pointers', $this->directory . '/invalidations'] as $directory) {
            if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
                return false;
            }
        }

        return is_writable($this->directory . '/objects')
            && is_writable($this->directory . '/pointers')
            && is_writable($this->directory . '/invalidations');
    }

    private function keyHash(string $key): string
    {
        return hash('sha256', 'page-snapshot-v2|' . $key);
    }

    private function pointerPath(string $key): string
    {
        return $this->directory . '/pointers/' . $this->keyHash($key) . '.json';
    }

    private function invalidationPath(string $key): string
    {
        return $this->directory . '/invalidations/' . $this->keyHash($key) . '.json';
    }

    private function artifactPath(string $key, string $revision, string $encoding): string
    {
        return $this->directory . '/objects/' . $this->keyHash($key) . '-' . $revision
            . ($encoding === 'gzip' ? '.json.gz' : '.json');
    }

    /** @return list<string> */
    private function pointerFiles(): array
    {
        $files = glob($this->directory . '/pointers/*.json');

        return is_array($files) ? array_values(array_filter($files, 'is_file')) : [];
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path, int $maxBytes): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (! is_string($contents) || $contents === '' || strlen($contents) > $maxBytes) {
            return null;
        }

        return $this->decode($contents);
    }

    /** @return array<string, mixed>|null */
    private function decode(string $contents): ?array
    {
        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /** @param array<string, mixed> $value */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function atomicWrite(string $path, string $contents): bool
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            return false;
        }

        $temporary = @tempnam($directory, '.snapshot-');
        if ($temporary === false) {
            return false;
        }

        try {
            if (@file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
                return false;
            }
            @chmod($temporary, 0640);

            return @rename($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function prune(string $key, string $activeRevision): void
    {
        $pattern = $this->directory . '/objects/' . $this->keyHash($key) . '-*';
        $files = glob($pattern);
        if (! is_array($files)) {
            return;
        }

        usort($files, static fn (string $left, string $right): int => (int) (@filemtime($right) ?: 0) <=> (int) (@filemtime($left) ?: 0));
        $keepPrevious = max(0, $this->retention - 1);
        $keptPrevious = 0;
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (str_contains($file, '-' . $activeRevision . '.')) {
                continue;
            }
            if ($keptPrevious < $keepPrevious) {
                $keptPrevious++;
                continue;
            }
            @unlink($file);
        }
    }

    private function clearObsoleteInvalidation(string $key, DateTimeImmutable $generatedAt): void
    {
        $markerPath = $this->invalidationPath($key);
        $marker = $this->readJson($markerPath, self::MAX_MARKER_BYTES);
        $invalidatedAt = $this->date($marker['invalidated_at'] ?? null);
        if ($invalidatedAt !== null && $invalidatedAt <= $generatedAt) {
            @unlink($markerPath);
        }
    }

    /** @param list<string> $scopes */
    private function invalidationDate(string $key, DateTimeImmutable $generatedAt, array $scopes): ?DateTimeImmutable
    {
        $marker = $this->readJson($this->invalidationPath($key), self::MAX_MARKER_BYTES);
        if ($marker === null) {
            return null;
        }
        $markerScopes = $this->strings($marker['scopes'] ?? []);
        if ($scopes !== [] && $markerScopes !== [] && array_intersect($scopes, $markerScopes) === []) {
            return null;
        }
        $invalidatedAt = $this->date($marker['invalidated_at'] ?? null);

        return $invalidatedAt !== null && $invalidatedAt > $generatedAt ? $invalidatedAt : null;
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

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
