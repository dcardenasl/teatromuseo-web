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
        if ($request->preview || $this->mode === 'sync') {
            return $this->synchronous->deliver($request);
        }

        $snapshot = $this->snapshot->deliver($request);
        if ($snapshot->isAvailable()) {
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
}
