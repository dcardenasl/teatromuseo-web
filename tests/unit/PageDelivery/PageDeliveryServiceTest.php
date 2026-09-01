<?php

declare(strict_types=1);

namespace Tests\Unit\PageDelivery;

use App\PageDelivery\PageDeliveryInterface;
use App\PageDelivery\PageDeliveryRequest;
use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\PageDeliveryService;
use App\PageDelivery\RegenerationLockInterface;
use App\PageDelivery\SnapshotBuilderInterface;
use App\PageDelivery\SnapshotBuildResult;
use PHPUnit\Framework\TestCase;

final class PageDeliveryServiceTest extends TestCase
{
    public function testSnapshotIsSelectedBeforeSynchronousComposition(): void
    {
        $synchronous = new RecordingDelivery(PageDeliveryResponse::failure(500, ['sync']));
        $snapshot = new RecordingDelivery(PageDeliveryResponse::success(
            ['title' => 'Snapshot'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home'],
        ));
        $lock = new RecordingLock();
        $service = new PageDeliveryService($synchronous, $snapshot, $lock);

        $result = $service->deliver(PageDeliveryRequest::home('es'));

        $this->assertSame('Snapshot', $result->page['title']);
        $this->assertSame(1, $snapshot->calls);
        $this->assertSame(0, $synchronous->calls);
        $this->assertSame(0, $lock->acquires);
    }

    public function testSynchronousFallbackIsSingleFlightGuarded(): void
    {
        $synchronous = new RecordingDelivery(PageDeliveryResponse::success(
            ['title' => 'Fresh composition'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home'],
        ));
        $snapshot = new RecordingDelivery(PageDeliveryResponse::failure(503, ['miss']));
        $lock = new RecordingLock('token');
        $service = new PageDeliveryService($synchronous, $snapshot, $lock, 'snapshot', true);

        $result = $service->deliver(PageDeliveryRequest::home('es'));

        $this->assertSame('Fresh composition', $result->page['title']);
        $this->assertSame(1, $lock->acquires);
        $this->assertSame(1, $lock->releases);
        $this->assertSame($lock->key, $lock->releasedKey);
    }

    public function testInvalidatedSnapshotIsServedWithoutVisitorRegeneration(): void
    {
        $stale = PageDeliveryResponse::success(
            ['title' => 'Stale snapshot'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home', 'cache' => 'stale'],
            ['domain' => 'web', 'state' => 'stale', 'stale' => true],
        );
        $builder = new FixedBuilder(SnapshotBuildResult::failed('must not run during delivery'));
        $service = new PageDeliveryService(
            synchronous: new RecordingDelivery(PageDeliveryResponse::failure(503, ['must not be called'])),
            snapshot: new RecordingDelivery($stale),
            lock: new RecordingLock(),
            mode: 'snapshot',
            allowSynchronousFallback: false,
            builder: $builder,
        );

        $result = $service->deliver(PageDeliveryRequest::home('es'));

        $this->assertSame('Stale snapshot', $result->page['title']);
        $this->assertSame(0, $builder->calls);
    }

    public function testPreviewBypassesSnapshotEvenWhenSnapshotModeIsConfigured(): void
    {
        $synchronous = new RecordingDelivery(PageDeliveryResponse::success(
            ['title' => 'Preview'],
            [],
            [],
            ['locale' => 'es', 'route' => 'home'],
        ));
        $snapshot = new RecordingDelivery(PageDeliveryResponse::failure(503, ['must not be called']));
        $service = new PageDeliveryService($synchronous, $snapshot, new RecordingLock());

        $result = $service->deliver(PageDeliveryRequest::home('es', true));

        $this->assertSame('Preview', $result->page['title']);
        $this->assertSame(0, $snapshot->calls);
    }

    public function testRoutePolicyBypassesSnapshotForUnlistedFullSiteBffRoutes(): void
    {
        $synchronous = new RecordingDelivery(PageDeliveryResponse::success(
            ['title' => 'BFF route'],
            [],
            [],
            ['locale' => 'es', 'route' => 'noticias/entrada'],
        ));
        $snapshot = new RecordingDelivery(PageDeliveryResponse::failure(503, ['must not be called']));
        $service = new PageDeliveryService(
            synchronous: $synchronous,
            snapshot: $snapshot,
            lock: new RecordingLock(),
            mode: 'snapshot',
            allowSynchronousFallback: true,
        );

        $result = $service->deliver(new PageDeliveryRequest(
            locale: 'es',
            route: 'noticias/entrada',
            snapshotEligible: false,
        ));

        $this->assertSame('BFF route', $result->page['title']);
        $this->assertSame(1, $synchronous->calls);
        $this->assertSame(0, $snapshot->calls);
    }

    public function testSkippedBuildReReadsSnapshotPublishedByAnotherWorker(): void
    {
        $synchronous = new RecordingDelivery(PageDeliveryResponse::failure(503, ['must not be called']));
        $snapshot = new SequenceDelivery([
            PageDeliveryResponse::failure(503, ['initial miss']),
            PageDeliveryResponse::success(
                ['title' => 'Published by another worker'],
                [],
                [],
                ['locale' => 'es', 'route' => 'home'],
            ),
        ]);
        $service = new PageDeliveryService(
            synchronous: $synchronous,
            snapshot: $snapshot,
            lock: new RecordingLock(),
            mode: 'snapshot',
            allowSynchronousFallback: true,
            builder: new FixedBuilder(SnapshotBuildResult::skipped('revision-2')),
        );

        $result = $service->deliver(PageDeliveryRequest::home('es'));

        $this->assertSame('Published by another worker', $result->page['title']);
        $this->assertSame(2, $snapshot->calls);
        $this->assertSame(0, $synchronous->calls);
    }
}

final class RecordingDelivery implements PageDeliveryInterface
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

final class SequenceDelivery implements PageDeliveryInterface
{
    public int $calls = 0;

    /** @param list<PageDeliveryResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function deliver(PageDeliveryRequest $request): PageDeliveryResponse
    {
        unset($request);
        $this->calls++;

        return array_shift($this->responses)
            ?? PageDeliveryResponse::failure(503, ['No scripted snapshot response.']);
    }
}

final class FixedBuilder implements SnapshotBuilderInterface
{
    public int $calls = 0;

    public function __construct(private readonly SnapshotBuildResult $result)
    {
    }

    public function build(PageDeliveryRequest $request, bool $force = false): SnapshotBuildResult
    {
        $this->calls++;
        unset($request, $force);

        return $this->result;
    }
}

final class RecordingLock implements RegenerationLockInterface
{
    public int $acquires = 0;
    public int $releases = 0;
    public string $key = '';
    public string $releasedKey = '';

    public function __construct(private readonly ?string $token = null)
    {
    }

    public function acquire(string $key): ?string
    {
        $this->acquires++;
        $this->key = $key;

        return $this->token;
    }

    public function release(string $key, string $token): void
    {
        $this->releases++;
        $this->releasedKey = $key;
        unset($token);
    }
}
