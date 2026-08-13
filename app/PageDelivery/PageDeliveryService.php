<?php

declare(strict_types=1);

namespace App\PageDelivery;

/**
 * Selection policy for public page delivery.
 *
 * Snapshot is always attempted first in snapshot mode. Synchronous fallback is
 * opt-in and guarded by the same regeneration lock seam that CACHE-02 will use.
 */
final class PageDeliveryService implements PageDeliveryInterface
{
    public function __construct(
        private readonly PageDeliveryInterface $synchronous,
        private readonly PageDeliveryInterface $snapshot,
        private readonly RegenerationLockInterface $lock,
        private readonly string $mode = 'snapshot',
        private readonly bool $allowSynchronousFallback = false,
        private readonly ?SnapshotBuilderInterface $builder = null,
    ) {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        // Free-text search/filter variants are never snapshot-eligible (see
        // PageDeliveryRequest::isSnapshotEligible()) — route them through the
        // same synchronous path as preview so they never reach the builder or
        // the snapshot store.
        if ($request->preview || $this->mode === 'sync' || ! $request->isSnapshotEligible()) {
            return $this->synchronous->deliver($request);
        }

        $snapshot = $this->snapshot->deliver($request);
        if ($snapshot->isAvailable()) {
            // Invalidation keeps the previous snapshot readable as a
            // resilience fallback. It must not be treated as a fresh hit
            // forever, otherwise a content invalidation can leave the public
            // site serving stale HTML until the stale TTL expires.
            if ($this->isFreshSnapshot($snapshot)) {
                return $snapshot;
            }

            if ($this->builder !== null) {
                $build = $this->builder->build($request);
                if ($build->state === 'built' && $build->response !== null) {
                    return $build->response;
                }

                // Another worker may have completed the rebuild while this
                // request was waiting for the single-flight lock.
                if ($build->state === 'skipped') {
                    $published = $this->snapshot->deliver($request);
                    if ($published->isAvailable() && $this->isFreshSnapshot($published)) {
                        return $published;
                    }
                }
            }

            // Preserve the stale snapshot when regeneration is unavailable or
            // fails, keeping the site renderable during an upstream outage.
            return $snapshot;
        }

        if (! $this->allowSynchronousFallback) {
            return $snapshot;
        }

        if ($this->builder !== null) {
            $build = $this->builder->build($request);
            if ($build->response !== null) {
                return $build->response;
            }

            // A different worker may have published the snapshot while this
            // builder was acquiring the single-flight lock. Re-read once after
            // a skipped build so this request observes the newly active pointer
            // instead of returning the miss that preceded the race.
            if ($build->state === 'skipped') {
                $published = $this->snapshot->deliver($request);
                if ($published->isAvailable()) {
                    return $published;
                }
            }

            return $snapshot;
        }

        $key = $request->cacheKey();
        $token = $this->lock->acquire($key);
        if ($token === null) {
            return $snapshot;
        }

        try {
            return $this->synchronous->deliver($request);
        } finally {
            $this->lock->release($key, $token);
        }
    }

    private function isFreshSnapshot(PageDeliveryResponse $response): bool
    {
        return ($response->source['state'] ?? null) !== 'stale'
            && ($response->source['stale'] ?? false) !== true
            && ($response->meta['cache'] ?? null) !== 'stale';
    }
}
