<?php

declare(strict_types=1);

namespace App\PageDelivery;

/** Reads only complete, non-preview snapshots and applies stale policy. */
final class SnapshotPageDeliveryAdapter implements PageDeliveryInterface
{
    public function __construct(
        private readonly SnapshotStoreInterface $store,
        private readonly ClockInterface $clock,
        private readonly int $staleTtl,
    ) {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        if ($request->preview) {
            return PageDeliveryResponse::failure(404, ['Preview cannot use a public snapshot.']);
        }

        $snapshot = $this->store->read($request->cacheKey());
        if ($snapshot === null) {
            return PageDeliveryResponse::failure(503, ['No public snapshot is available.'], [
                'locale' => $request->locale,
                'route' => $request->route,
                'cache' => 'miss',
            ]);
        }

        $response = PageDeliveryResponse::fromEnvelope($snapshot->envelope);
        if ($response === null || ! $response->isAvailable()) {
            return PageDeliveryResponse::failure(503, ['The public snapshot is invalid.'], [
                'locale' => $request->locale,
                'route' => $request->route,
                'cache' => 'invalid',
            ]);
        }

        $snapshotQuery = is_array($response->meta['query'] ?? null) ? $response->meta['query'] : [];
        if ((string) ($response->meta['locale'] ?? '') !== $request->locale
            || (string) ($response->meta['route'] ?? '') !== $request->route
            || $snapshotQuery !== $request->query) {
            return PageDeliveryResponse::failure(503, ['The public snapshot identity does not match the request.']);
        }

        $now = $this->clock->now();
        $fresh = $snapshot->invalidatedAt === null && $now <= $snapshot->expiresAt;
        $staleUntil = $snapshot->expiresAt->modify('+' . max(0, $this->staleTtl) . ' seconds');
        if ($snapshot->invalidatedAt !== null) {
            $staleUntil = max(
                $staleUntil->getTimestamp(),
                $snapshot->invalidatedAt->modify('+' . max(0, $this->staleTtl) . ' seconds')->getTimestamp(),
            );
            $staleUntil = (new \DateTimeImmutable())->setTimestamp($staleUntil);
        }
        if (! $fresh && ($this->staleTtl <= 0 || $now > $staleUntil)) {
            return PageDeliveryResponse::failure(503, ['The public snapshot has expired.'], [
                'locale' => $request->locale,
                'route' => $request->route,
                'cache' => 'expired',
            ]);
        }

        $state = $fresh ? 'fresh' : 'stale';

        return new PageDeliveryResponse(
            status: 200,
            page: $response->page,
            layout: $response->layout,
            blockContext: $response->blockContext,
            meta: array_merge($response->meta, [
                'cache' => $state,
                'snapshot_revision' => $snapshot->revision,
                'etag' => $snapshot->etag,
                'generated_at' => $snapshot->generatedAt->format(DATE_ATOM),
                'expires_at' => $snapshot->expiresAt->format(DATE_ATOM),
            ]),
            source: [
                'domain' => 'web',
                'state' => $state,
                'stale' => ! $fresh,
            ],
            messages: $response->messages,
        );
    }
}
