<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\ClockInterface;
use App\PageDelivery\FileRegenerationLock;
use App\PageDelivery\FileSnapshotStore;
use App\PageDelivery\PageDeliveryInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\SnapshotBuilder;
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
