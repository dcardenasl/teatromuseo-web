<?php

declare(strict_types=1);

namespace App\PageDelivery;

use DateTimeInterface;

/**
 * Builds one complete public delivery and publishes it as a snapshot.
 *
 * The lock is acquired before any upstream work. A competing worker returns
 * `busy` immediately and leaves the current active pointer untouched. This is
 * the single-flight boundary used by CLI warm-up, queue jobs and the optional
 * controlled synchronous fallback.
 */
final class SnapshotBuilder implements SnapshotBuilderInterface
{
    /** @param list<string> $scopes */
    public function __construct(
        private readonly PageDeliveryInterface $synchronous,
        private readonly SnapshotPublisherInterface $publisher,
        private readonly RegenerationLockInterface $lock,
        private readonly ClockInterface $clock,
        private readonly int $ttl,
        private readonly array $scopes,
    ) {
    }

    public function build(PageDeliveryRequest $request, bool $force = false): SnapshotBuildResult
    {
        if ($request->preview) {
            return SnapshotBuildResult::failed('Preview deliveries are never persisted as public snapshots.');
        }
        if ($this->ttl <= 0) {
            return SnapshotBuildResult::failed('Public snapshot TTL is disabled.');
        }
        if (($this->publisher->status()['enabled'] ?? false) !== true) {
            return SnapshotBuildResult::failed('The shared snapshot backend is not enabled.');
        }

        $active = $this->publisher->read($request->cacheKey());
        if (! $force && $active !== null && $active->invalidatedAt === null && $this->clock->now() <= $active->expiresAt) {
            return SnapshotBuildResult::skipped($active->revision);
        }

        $key = $request->cacheKey();
        $token = $this->lock->acquire($key);
        if ($token === null) {
            return SnapshotBuildResult::busy();
        }

        try {
            // Re-check after acquiring the lock. Another builder may have
            // published while this process was waiting for a stale lock.
            $active = $this->publisher->read($key);
            if (! $force && $active !== null && $active->invalidatedAt === null && $this->clock->now() <= $active->expiresAt) {
                return SnapshotBuildResult::skipped($active->revision);
            }

            // Capture the build start, not the completion time. If a
            // publication invalidates the identity while upstream composition
            // is running, the resulting snapshot remains marked stale and
            // cannot become the fresh public version by winning a race.
            $buildStartedAt = $this->clock->now();
            $response = $this->synchronous->deliver($request);
            if (! $response->isAvailable() || $response->page === null) {
                return SnapshotBuildResult::failed('Synchronous page composition did not produce an available page.', $response);
            }
            if (! $this->matchesRequest($response, $request)) {
                return SnapshotBuildResult::failed('Synchronous composition returned a different snapshot identity.');
            }
            if (($response->source['stale'] ?? false) === true
                || ($response->source['state'] ?? null) === 'stale') {
                return SnapshotBuildResult::failed(
                    'Synchronous composition used stale upstream data; the public snapshot was not published.',
                    $response,
                );
            }
            $sourceRevision = hash('sha256', $this->canonical([
                'page' => $response->page,
                'layout' => $response->layout,
                'block_context' => $response->blockContext,
                'source' => $response->source,
            ]));
            $revision = hash('sha256', 'snapshot-v2|' . $key . '|' . $sourceRevision);
            $now = $buildStartedAt;
            $response = new PageDeliveryResponse(
                status: $response->status,
                page: $response->page,
                layout: $response->layout,
                blockContext: $response->blockContext,
                meta: array_merge($response->meta, [
                    'source_revision' => $sourceRevision,
                    'source_revisions' => ['web_composition' => $sourceRevision],
                    'snapshot_revision' => $revision,
                    'generated_at' => $now->format(DateTimeInterface::ATOM),
                    'expires_at' => $now->modify('+' . $this->ttl . ' seconds')->format(DateTimeInterface::ATOM),
                ]),
                source: array_merge($response->source, [
                    'state' => 'fresh',
                    'stale' => false,
                ]),
                messages: $response->messages,
            );

            $snapshot = new PageSnapshot(
                key: $key,
                envelope: $response->envelope(),
                generatedAt: $now,
                expiresAt: $now->modify('+' . $this->ttl . ' seconds'),
                revision: $revision,
                scopes: array_values(array_unique($this->scopes)),
            );
            if (! $this->publisher->publish($snapshot)) {
                return SnapshotBuildResult::failed('Snapshot publication failed; the active snapshot was preserved.', $response);
            }

            return SnapshotBuildResult::built($response, $revision);
        } catch (\Throwable $exception) {
            log_message('error', 'Public snapshot build failed: {message}', [
                'message' => $exception->getMessage(),
                'key' => $key,
            ]);

            return SnapshotBuildResult::failed('Snapshot build failed.', null);
        } finally {
            $this->lock->release($key, $token);
        }
    }

    private function matchesRequest(PageDeliveryResponse $response, PageDeliveryRequest $request): bool
    {
        return ($response->meta['locale'] ?? null) === $request->locale
            && ($response->meta['route'] ?? null) === $request->route
            && ($response->meta['query'] ?? []) === $request->query;
    }

    /** @param array<string, mixed> $value */
    private function canonical(array $value): string
    {
        return (string) json_encode($this->sortRecursive($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        return $value;
    }
}
