<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\FileSnapshotStore;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\PageSnapshot;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FileSnapshotStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/tm-snapshots-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0750, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testPublishesAndReadsTheActiveCompressedSnapshot(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'gzip');
        $snapshot = $this->snapshot($request, hash('sha256', 'first'), 'First');

        self::assertTrue($store->publish($snapshot));
        $read = $store->read($request->cacheKey());

        self::assertNotNull($read);
        self::assertSame('First', $read->envelope['data']['page']['title']);
        self::assertSame($snapshot->revision, $read->revision);
        self::assertNotNull($read->etag);
        self::assertSame(['pages', 'menus'], $read->scopes);
        self::assertFileExists($this->directory . '/pointers/' . hash('sha256', 'page-snapshot-v2|' . $request->cacheKey()) . '.json');
    }

    public function testInvalidationMarksOnlySnapshotsInTheRequestedScope(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        self::assertTrue($store->publish($this->snapshot($request, hash('sha256', 'stable'), 'Stable')));

        self::assertSame(0, $store->invalidateScopes(['events']));
        self::assertNull($store->read($request->cacheKey())?->invalidatedAt);

        self::assertSame(1, $store->invalidateScopes(['pages']));
        self::assertNotNull($store->read($request->cacheKey())?->invalidatedAt);
    }

    public function testInvalidationCanBeNarrowedByLocaleAndRoute(): void
    {
        $store = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        $spanish = PageDeliveryRequest::home('es');
        $english = PageDeliveryRequest::home('en');
        self::assertTrue($store->publish($this->snapshot($spanish, hash('sha256', 'es'), 'ES')));
        self::assertTrue($store->publish($this->snapshot($english, hash('sha256', 'en'), 'EN')));

        self::assertSame(1, $store->invalidateScopes(['pages'], ['es'], ['home']));
        self::assertNotNull($store->read($spanish->cacheKey())?->invalidatedAt);
        self::assertNull($store->read($english->cacheKey())?->invalidatedAt);
    }

    public function testFailedPublicationDoesNotReplaceTheActiveSnapshot(): void
    {
        $request = PageDeliveryRequest::home('es');
        $store = new FileSnapshotStore($this->directory, 2_048, 2, 'none');
        self::assertTrue($store->publish($this->snapshot($request, hash('sha256', 'valid'), 'Valid')));

        $oversized = $this->snapshot($request, hash('sha256', 'oversized'), str_repeat('x', 5000));
        self::assertFalse($store->publish($oversized));
        self::assertSame('Valid', $store->read($request->cacheKey())?->envelope['data']['page']['title'] ?? null);
    }

    public function testIndependentWorkersReadTheSamePublishedPointer(): void
    {
        $request = PageDeliveryRequest::home('es');
        $workerA = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');
        $workerB = new FileSnapshotStore($this->directory, 1_048_576, 2, 'none');

        self::assertTrue($workerA->publish($this->snapshot($request, hash('sha256', 'shared'), 'Shared')));
        $read = $workerB->read($request->cacheKey());

        self::assertNotNull($read);
        self::assertSame(hash('sha256', 'shared'), $read->revision);
        self::assertSame('Shared', $read->envelope['data']['page']['title']);
    }

    public function testIndependentWorkersCanOnlyAcquireOneRegenerationLock(): void
    {
        $lockDirectory = $this->directory . '/locks';
        $workerA = new \App\PageDelivery\FileRegenerationLock($lockDirectory, 900);
        $workerB = new \App\PageDelivery\FileRegenerationLock($lockDirectory, 900);

        $token = $workerA->acquire('homepage:es');
        self::assertNotNull($token);
        self::assertNull($workerB->acquire('homepage:es'));

        $workerA->release('homepage:es', $token);
        self::assertNotNull($workerB->acquire('homepage:es'));
    }

    private function snapshot(PageDeliveryRequest $request, string $revision, string $title): PageSnapshot
    {
        $response = PageDeliveryResponse::success(
            ['title' => $title],
            [],
            [],
            ['locale' => $request->locale, 'route' => $request->route, 'query' => $request->query],
        );

        return new PageSnapshot(
            key: $request->cacheKey(),
            envelope: $response->envelope(),
            generatedAt: new DateTimeImmutable('2026-08-09T12:00:00+00:00'),
            expiresAt: new DateTimeImmutable('2026-08-09T13:00:00+00:00'),
            revision: $revision,
            scopes: ['pages', 'menus'],
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if (! is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
