<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Interfaces\BlockAnalyzerInterface;
use App\Interfaces\SmartPrefetchInterface;
use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetchService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BlockPrefetchServiceTest extends TestCase
{
    public function testBatchesCmsGridRequestsAndKeepsResultsByBlockPath(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return count($requests) === 2
                    && ($requests[0]['path'] ?? '') === 'public/es/entries/news'
                    && ($requests[1]['path'] ?? '') === 'public/es/entries/archive';
            }))
            ->willReturn([
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 1, 'title' => 'News item']],
                    'meta' => ['pagination' => ['total' => 1]],
                    'messages' => [],
                ],
                [
                    'ok' => true,
                    'status' => 200,
                    'data' => [['id' => 2, 'title' => 'Event item']],
                    'meta' => [],
                    'messages' => [],
                ],
            ]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([
            [
                'block_key' => 'collection_grid',
                'block_config' => ['collection_key' => 'news', 'items_limit' => 3],
                'children' => [],
            ],
            [
                'block_key' => 'collection_grid',
                'block_config' => ['collection_key' => 'archive', 'items_limit' => 4],
                'children' => [],
            ],
        ], 'es');

        $this->assertSame('News item', $result['0']['data'][0]['title']);
        $this->assertSame('Event item', $result['1']['data'][0]['title']);
        $this->assertSame(['pagination' => ['total' => 1]], $result['0']['meta']);
    }

    public function testRoutesCatalogAndEventGridsToTheirOwningClients(): void
    {
        /** @var WebApiClientInterface&MockObject $catalog */
        $catalog = $this->createMock(WebApiClientInterface::class);
        /** @var WebApiClientInterface&MockObject $event */
        $event = $this->createMock(WebApiClientInterface::class);

        $catalog->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => ($requests[0]['path'] ?? '') === 'public/catalog/collection-items'))
            ->willReturn([['ok' => true, 'status' => 200, 'data' => [['id' => 7]], 'meta' => [], 'messages' => []]]);
        $event->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => ($requests[0]['path'] ?? '') === 'public/events'))
            ->willReturn([['ok' => true, 'status' => 200, 'data' => [['id' => 8]], 'meta' => [], 'messages' => []]]);

        $service = new BlockPrefetchService(['catalog' => $catalog, 'event' => $event]);
        $result = $service->prefetch([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'museo'], 'children' => []],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'cartelera'], 'children' => []],
        ]);

        $this->assertSame(7, $result['0']['data'][0]['id']);
        $this->assertSame(8, $result['1']['data'][0]['id']);
    }

    public function testIncludesNestedDynamicBlocks(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => count($requests) === 1))
            ->willReturn([['ok' => true, 'status' => 200, 'data' => [['id' => 9]], 'meta' => [], 'messages' => []]]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([[
            'block_key' => 'container',
            'children' => [[
                'block_key' => 'collection_timeline',
                'block_config' => ['collection_key' => 'archive'],
                'children' => [],
            ]],
        ]]);

        $this->assertSame(9, $result['0.0']['data'][0]['id']);
    }

    public function testPrefetchContextCombinesListsAndDetailResources(): void
    {
        $analyzer = $this->createMock(BlockAnalyzerInterface::class);
        $smartPrefetch = $this->createMock(SmartPrefetchInterface::class);
        $analyzer->expects($this->once())
            ->method('analyze')
            ->with($this->isType('array'), 'es')
            ->willReturn(['events' => ['ids' => [10], 'fields' => ['id']]]);
        $smartPrefetch->expects($this->once())
            ->method('prefetch')
            ->with(['events' => ['ids' => [10], 'fields' => ['id']]], 'es')
            ->willReturn(['events' => [10 => ['id' => 10, 'title' => 'Event']]]);

        $service = new BlockPrefetchService([], $analyzer, $smartPrefetch);
        $context = $service->prefetchContext([
            ['block_key' => 'event_item_header', 'block_data' => ['event_id' => 10]],
        ], 'es');

        $this->assertArrayHasKey('block_prefetch', $context);
        $this->assertSame('Event', $context['events'][10]['title']);
    }
}
