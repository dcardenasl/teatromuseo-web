<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\ClockInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\PageSnapshot;
use App\PageDelivery\SnapshotPageDeliveryAdapter;
use App\PageDelivery\SnapshotStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SnapshotPageDeliveryAdapterTest extends TestCase
{
    public function testFreshSnapshotIsDeliveredWithFreshState(): void
    {
        $request = PageDeliveryRequest::home('es');
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00'));
        $snapshot = $this->snapshot($request, new DateTimeImmutable('2026-08-09T13:00:00+00:00'));
        $adapter = new SnapshotPageDeliveryAdapter(new InMemorySnapshotStore($snapshot), $clock, 3600);

        $result = $adapter->deliver($request);

        $this->assertTrue($result->isAvailable());
        $this->assertSame('fresh', $result->source['state']);
        $this->assertFalse($result->source['stale']);
    }

    public function testExpiredSnapshotIsServedStaleOnlyWithinConfiguredWindow(): void
    {
        $request = PageDeliveryRequest::home('es');
        $snapshot = $this->snapshot($request, new DateTimeImmutable('2026-08-09T10:00:00+00:00'));
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-09T10:30:00+00:00'));

        $result = (new SnapshotPageDeliveryAdapter(new InMemorySnapshotStore($snapshot), $clock, 3600))
            ->deliver($request);
        $this->assertTrue($result->isAvailable());
        $this->assertSame('stale', $result->source['state']);
        $this->assertTrue($result->source['stale']);

        $expired = (new SnapshotPageDeliveryAdapter(new InMemorySnapshotStore($snapshot), new FrozenClock(
            new DateTimeImmutable('2026-08-09T13:01:00+00:00'),
        ), 3600))->deliver($request);
        $this->assertFalse($expired->isAvailable());
        $this->assertSame(503, $expired->status);
    }

    /** @param DateTimeImmutable $expiresAt */
    private function snapshot(PageDeliveryRequest $request, DateTimeImmutable $expiresAt): PageSnapshot
    {
        $response = PageDeliveryResponse::success(
            ['title' => 'Published snapshot'],
            ['settings' => []],
            ['block_prefetch' => [], 'block_prefetch_complete' => true],
            ['locale' => $request->locale, 'route' => $request->route],
        );

        return new PageSnapshot(
            key: $request->cacheKey(),
            envelope: $response->envelope(),
            generatedAt: $expiresAt->modify('-300 seconds'),
            expiresAt: $expiresAt,
            revision: 'rev-1',
        );
    }
}

final class FrozenClock implements ClockInterface
{
    public function __construct(private readonly DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}

final class InMemorySnapshotStore implements SnapshotStoreInterface
{
    public function __construct(private readonly PageSnapshot $snapshot)
    {
    }

    public function read(string $key): ?PageSnapshot
    {
        return $key === $this->snapshot->key ? $this->snapshot : null;
    }
}
