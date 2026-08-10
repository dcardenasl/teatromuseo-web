<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\ClockInterface;
use App\PageDelivery\FileRegenerationLock;
use App\PageDelivery\FileSnapshotStore;
use App\PageDelivery\PageDeliveryInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\PageSnapshot;
use App\PageDelivery\RegenerationLockInterface;
use App\PageDelivery\SnapshotBuilder;
use App\PageDelivery\SnapshotPublisherInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SnapshotBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/tm-builder-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0750, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testBuildPublishesOneCompleteSnapshotAndSecondRunIsIdempotent(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        $adapter = new FixedDelivery(PageDeliveryResponse::success(
            ['title' => 'Built homepage'],
            ['settings' => []],
            ['block_prefetch' => []],
            ['locale' => 'es', 'route' => 'home', 'query' => []],
        ));
        $builder = new SnapshotBuilder(
            synchronous: $adapter,
            publisher: $store,
            lock: new FileRegenerationLock($this->directory . '/locks', 900),
            clock: new FixedClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00')),
            ttl: 300,
            scopes: ['pages', 'menus'],
        );

        $first = $builder->build($request);
        $second = $builder->build($request);

        self::assertSame('built', $first->state);
        self::assertSame('skipped', $second->state);
        self::assertSame(1, $adapter->calls);
        self::assertSame('Built homepage', $store->read($request->cacheKey())?->envelope['data']['page']['title'] ?? null);
        self::assertSame($first->revision, $store->read($request->cacheKey())?->revision);
    }

    public function testPreviewIsRejectedBeforeLockOrUpstreamWork(): void
    {
        $adapter = new FixedDelivery(PageDeliveryResponse::failure(500, ['not called']));
        $builder = new SnapshotBuilder(
            synchronous: $adapter,
            publisher: new FileSnapshotStore($this->directory),
            lock: new FileRegenerationLock($this->directory . '/locks'),
            clock: new FixedClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00')),
            ttl: 300,
            scopes: ['pages'],
        );

        $result = $builder->build(PageDeliveryRequest::home('es', true));

        self::assertSame('failed', $result->state);
        self::assertSame(0, $adapter->calls);
    }

    public function testStaleCompositionIsNotPublishedAsAFreshSnapshot(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        $adapter = new FixedDelivery(PageDeliveryResponse::success(
            ['title' => 'Stale homepage'],
            ['settings' => []],
            ['block_prefetch' => []],
            ['locale' => 'es', 'route' => 'home', 'query' => []],
            ['state' => 'stale', 'stale' => true],
        ));
        $builder = new SnapshotBuilder(
            synchronous: $adapter,
            publisher: $store,
            lock: new FileRegenerationLock($this->directory . '/locks', 900),
            clock: new FixedClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00')),
            ttl: 300,
            scopes: ['pages'],
        );

        $result = $builder->build($request);

        self::assertSame('failed', $result->state);
        self::assertStringContainsString('stale upstream data', (string) $result->message);
        self::assertNull($store->read($request->cacheKey()));
    }

    public function testCompetingBuilderReturnsBusyAndPreservesExpiredActiveSnapshot(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        self::assertTrue($store->publish($this->snapshot(
            $request,
            hash('sha256', 'active'),
            'Active before competing build',
            new DateTimeImmutable('2026-08-09T10:00:00+00:00'),
            new DateTimeImmutable('2026-08-09T11:00:00+00:00'),
        )));
        $adapter = new FixedDelivery(PageDeliveryResponse::success(
            ['title' => 'Must not compose'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home', 'query' => []],
        ));
        $builder = new SnapshotBuilder(
            synchronous: $adapter,
            publisher: $store,
            lock: new BusyLock(),
            clock: new FixedClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00')),
            ttl: 300,
            scopes: ['pages'],
        );

        $result = $builder->build($request);

        self::assertSame('busy', $result->state);
        self::assertSame(0, $adapter->calls);
        self::assertSame(
            'Active before competing build',
            $store->read($request->cacheKey())?->envelope['data']['page']['title'] ?? null,
        );
    }

    public function testSecondReadAfterLockAcquisitionAvoidsDuplicateComposition(): void
    {
        $request = PageDeliveryRequest::home('es');
        $publisher = new SequencedPublisher([
            $this->snapshot(
                $request,
                hash('sha256', 'initial'),
                'Initial',
                new DateTimeImmutable('2026-08-09T10:00:00+00:00'),
                new DateTimeImmutable('2026-08-09T11:00:00+00:00'),
            ),
            $this->snapshot(
                $request,
                hash('sha256', 'published'),
                'Published while waiting',
                new DateTimeImmutable('2026-08-09T12:00:00+00:00'),
                new DateTimeImmutable('2026-08-09T13:00:00+00:00'),
            ),
        ]);
        $adapter = new FixedDelivery(PageDeliveryResponse::success(
            ['title' => 'Must not compose'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home', 'query' => []],
        ));
        $builder = new SnapshotBuilder(
            synchronous: $adapter,
            publisher: $publisher,
            lock: new HeldLock(),
            clock: new FixedClock(new DateTimeImmutable('2026-08-09T12:00:00+00:00')),
            ttl: 300,
            scopes: ['pages'],
        );

        $result = $builder->build($request);

        self::assertSame('skipped', $result->state);
        self::assertSame(0, $adapter->calls);
        self::assertSame(2, $publisher->reads);
        self::assertSame(0, $publisher->publishes);
    }

    private function snapshot(
        PageDeliveryRequest $request,
        string $revision,
        string $title,
        DateTimeImmutable $generatedAt,
        DateTimeImmutable $expiresAt,
    ): PageSnapshot {
        $response = PageDeliveryResponse::success(
            ['title' => $title],
            [],
            [],
            ['locale' => $request->locale, 'route' => $request->route, 'query' => $request->query],
        );

        return new PageSnapshot(
            key: $request->cacheKey(),
            envelope: $response->envelope(),
            generatedAt: $generatedAt,
            expiresAt: $expiresAt,
            revision: $revision,
            scopes: ['pages'],
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $entries = scandir($directory);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $directory . '/' . $entry;
                is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
            }
        }
        @rmdir($directory);
    }
}

final class BusyLock implements RegenerationLockInterface
{
    public function acquire(string $key): ?string
    {
        unset($key);

        return null;
    }

    public function release(string $key, string $token): void
    {
        unset($key, $token);
    }
}

final class HeldLock implements RegenerationLockInterface
{
    public function acquire(string $key): ?string
    {
        unset($key);

        return 'held-token';
    }

    public function release(string $key, string $token): void
    {
        unset($key, $token);
    }
}

final class SequencedPublisher implements SnapshotPublisherInterface
{
    public int $reads = 0;
    public int $publishes = 0;

    /** @param list<PageSnapshot> $snapshots */
    public function __construct(private array $snapshots)
    {
    }

    public function read(string $key): ?PageSnapshot
    {
        $this->reads++;
        $snapshot = array_shift($this->snapshots);

        return $snapshot !== null && $snapshot->key === $key ? $snapshot : null;
    }

    public function publish(PageSnapshot $snapshot): bool
    {
        unset($snapshot);
        $this->publishes++;

        return true;
    }

    public function invalidateScopes(array $scopes, array $locales = [], array $routes = []): int
    {
        unset($scopes, $locales, $routes);

        return 0;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        return ['enabled' => true];
    }
}

final class FixedDelivery implements PageDeliveryInterface
{
    public int $calls = 0;

    public function __construct(private readonly PageDeliveryResponse $response)
    {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        unset($request);
        $this->calls++;

        return $this->response;
    }
}

final class FixedClock implements ClockInterface
{
    public function __construct(private readonly DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
