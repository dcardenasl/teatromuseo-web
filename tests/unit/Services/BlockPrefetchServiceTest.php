<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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
                $this->success([['id' => 1, 'title' => 'News item']], ['pagination' => ['total' => 1]]),
                $this->success([['id' => 2, 'title' => 'Archive item']]),
            ]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news', 'items_limit' => 3]],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'archive', 'items_limit' => 4]],
        ], 'es');

        $this->assertTrue($result['0']['ok']);
        $this->assertSame('News item', $result['0']['data'][0]['title']);
        $this->assertSame('Archive item', $result['1']['data'][0]['title']);
        $this->assertSame(['pagination' => ['total' => 1]], $result['0']['meta']);
        $this->assertSame('collection_grid', $result['0']['instance']['type']);
        $this->assertSame(3, $result['0']['instance']['limit']);
        $this->assertSame(1, $result['0']['instance']['page']);
        $this->assertSame('archive', $result['1']['instance']['config']['collection_key']);
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
            ->willReturn([$this->success([['id' => 7]])]);
        $event->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => ($requests[0]['path'] ?? '') === 'public/events'))
            ->willReturn([$this->success([['id' => 8]])]);

        $service = new BlockPrefetchService(['catalog' => $catalog, 'event' => $event]);
        $result = $service->prefetch([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'museo']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'cartelera']],
        ]);

        $this->assertSame(7, $result['0']['data'][0]['id']);
        $this->assertSame(8, $result['1']['data'][0]['id']);
    }

    public function testCollectionListingPrefetchesEntriesAndFacets(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                return count($requests) === 2
                    && ($requests[0]['path'] ?? '') === 'public/catalog/collection-items'
                    && ($requests[1]['path'] ?? '') === 'public/catalog/categories';
            }))
            ->willReturn([
                $this->success([['id' => 10, 'name' => 'Work']], ['pagination' => ['current_page' => 1]]),
                $this->success([['id' => 4, 'slug' => 'works', 'name' => 'Works']]),
            ]);

        $service = new BlockPrefetchService(['catalog' => $client]);
        $result = $service->prefetch([[
            'block_key' => 'collection_listing',
            'block_config' => [
                'source_type' => 'catalog_items',
                'per_page' => 12,
                'show_categories' => true,
            ],
        ]], 'es');

        $this->assertTrue($result['0']['ok']);
        $this->assertSame('Work', $result['0']['data'][0]['name']);
        $this->assertSame('Works', $result['0']['facets']['categories'][0]['name']);
    }

    public function testDetailBlocksAreReturnedByBlockPath(): void
    {
        $event = $this->createMock(WebApiClientInterface::class);
        $event->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => ($requests[0]['path'] ?? '') === 'public/events'))
            ->willReturn([$this->success([['id' => 201, 'title' => 'Prefetched event']])]);

        $service = new BlockPrefetchService(['event' => $event]);
        $result = $service->prefetch([[
            'block_key' => 'event_item_header',
            'block_data' => ['event_id' => 201],
        ]]);

        $this->assertTrue($result['0']['ok']);
        $this->assertSame('Prefetched event', $result['0']['data'][0]['title']);
    }

    public function testIdenticalRequestsAreDeduplicatedButMappedToBothBlocks(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => count($requests) === 1))
            ->willReturn([$this->success([['id' => 1]])]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
        ]);

        $this->assertSame($result['0']['data'], $result['1']['data']);
    }

    public function testFailedRequestStillReturnsAnExplicitEmptyEnvelope(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->willReturn([[
                'ok' => false,
                'status' => 404,
                'data' => null,
                'meta' => [],
                'messages' => ['Not found'],
            ]]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([[
            'block_key' => 'collection_grid',
            'block_config' => ['collection_key' => 'missing'],
        ]]);

        $this->assertArrayHasKey('0', $result);
        $this->assertFalse($result['0']['ok']);
        $this->assertSame(404, $result['0']['status']);
        $this->assertSame([], $result['0']['data']);
    }

    public function testIncludesNestedDynamicBlocks(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->willReturn([$this->success([['id' => 9]])]);

        $service = new BlockPrefetchService(['cms' => $client]);
        $result = $service->prefetch([[
            'block_key' => 'container',
            'children' => [[
                'block_key' => 'collection_timeline',
                'block_config' => ['collection_key' => 'archive'],
            ]],
        ]]);

        $this->assertSame(9, $result['0.0']['data'][0]['id']);
    }

    /** @param list<array<string, mixed>> $data */
    private function success(array $data, array $meta = []): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'data' => $data,
            'meta' => $meta,
            'messages' => [],
        ];
    }
}
