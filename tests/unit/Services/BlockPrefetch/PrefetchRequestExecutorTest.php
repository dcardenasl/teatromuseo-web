<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\PrefetchRequestExecutor;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

/**
 * Covers the per-client `multiGet()` grouping fallback, which is what every
 * test double (WebApiClientInterface mocks, never `instanceof WebApiClient`)
 * exercises. The `WebApiClient::multiGetAcross()` cross-client curl_multi
 * wave is production-only real-HTTP behavior with no unit seam of its own —
 * same as before this class existed.
 */
final class PrefetchRequestExecutorTest extends TestCase
{
    private BlockResultMaterializer $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->results = new BlockResultMaterializer(new RequestQueryReader());
    }

    public function testGroupsRequestsByClientAndPreservesOriginalIndexes(): void
    {
        $cms = $this->createMock(WebApiClientInterface::class);
        $cms->expects($this->once())
            ->method('multiGet')
            ->with([
                ['path' => 'a', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
                ['path' => 'b', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
            ])
            ->willReturn([
                ['ok' => true, 'status' => 200, 'data' => ['from' => 'a'], 'meta' => [], 'messages' => []],
                ['ok' => true, 'status' => 200, 'data' => ['from' => 'b'], 'meta' => [], 'messages' => []],
            ]);
        $catalog = $this->createMock(WebApiClientInterface::class);
        $catalog->expects($this->once())
            ->method('multiGet')
            ->with([['path' => 'c', 'query' => [], 'cacheTtl' => 60, 'scope' => 's']])
            ->willReturn([['ok' => true, 'status' => 200, 'data' => ['from' => 'c'], 'meta' => [], 'messages' => []]]);

        $executor = new PrefetchRequestExecutor(['cms' => $cms, 'catalog' => $catalog], $this->results);

        $responses = $executor->execute([
            ['client' => 'cms', 'path' => 'a', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
            ['client' => 'catalog', 'path' => 'c', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
            ['client' => 'cms', 'path' => 'b', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
        ]);

        $this->assertSame(['from' => 'a'], $responses[0]['data']);
        $this->assertSame(['from' => 'c'], $responses[1]['data']);
        $this->assertSame(['from' => 'b'], $responses[2]['data']);
    }

    public function testUnavailableClientYieldsAn503FailedResultPerRequest(): void
    {
        $executor = new PrefetchRequestExecutor([], $this->results);

        $responses = $executor->execute([
            ['client' => 'missing', 'path' => 'a', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
        ]);

        $this->assertFalse($responses[0]['ok']);
        $this->assertSame(503, $responses[0]['status']);
    }

    public function testNonArrayClientResponseBecomesA502FailedResult(): void
    {
        $cms = $this->createMock(WebApiClientInterface::class);
        $cms->method('multiGet')->willReturn([false]);

        $executor = new PrefetchRequestExecutor(['cms' => $cms], $this->results);

        $responses = $executor->execute([
            ['client' => 'cms', 'path' => 'a', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
        ]);

        $this->assertFalse($responses[0]['ok']);
        $this->assertSame(502, $responses[0]['status']);
    }

    public function testOffsetSkipsAlreadyExecutedRequestsAndKeepsAbsoluteIndexes(): void
    {
        $cms = $this->createMock(WebApiClientInterface::class);
        $cms->expects($this->once())
            ->method('multiGet')
            ->with([['path' => 'b', 'query' => [], 'cacheTtl' => 60, 'scope' => 's']])
            ->willReturn([['ok' => true, 'status' => 200, 'data' => ['from' => 'b'], 'meta' => [], 'messages' => []]]);

        $executor = new PrefetchRequestExecutor(['cms' => $cms], $this->results);

        $responses = $executor->execute([
            ['client' => 'cms', 'path' => 'a', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
            ['client' => 'cms', 'path' => 'b', 'query' => [], 'cacheTtl' => 60, 'scope' => 's'],
        ], 1);

        $this->assertArrayNotHasKey(0, $responses);
        $this->assertSame(['from' => 'b'], $responses[1]['data']);
    }

    public function testEmptyRequestListReturnsEmptyResponses(): void
    {
        $executor = new PrefetchRequestExecutor([], $this->results);

        $this->assertSame([], $executor->execute([]));
    }
}
