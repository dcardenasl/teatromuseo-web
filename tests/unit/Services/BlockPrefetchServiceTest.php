<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetchService;
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
                    && ($requests[0]['path'] ?? '') === 'public-read/es/entries/news'
                    && ($requests[1]['path'] ?? '') === 'public-read/es/entries/archive'
                    && str_contains((string) ($requests[0]['query']['fields'] ?? ''), 'excerpt')
                    && ! str_contains((string) ($requests[0]['query']['fields'] ?? ''), 'summary')
                    && ! str_contains((string) ($requests[0]['query']['fields'] ?? ''), 'entry.')
                    && ! str_contains((string) ($requests[0]['query']['fields'] ?? ''), 'block.');
            }))
            ->willReturn([
                $this->success([['id' => 1, 'title' => 'News item']], ['pagination' => ['total' => 1]]),
                $this->success([['id' => 2, 'title' => 'Archive item']]),
            ]);

        $service = new BlockPrefetchService($client);
        $result = $service->prefetch([
            ['block_key' => 'collection_grid', 'block_config' => [
                'collection_key' => 'news',
                'items_limit' => 3,
                'listing_projection' => [
                    'slots' => ['title' => 'entry.title', 'summary' => 'entry.excerpt', 'date' => 'block.news.start_date'],
                    'order' => ['field' => 'block.news.start_date'],
                ],
            ]],
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
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => in_array(
                $requests[0]['path'] ?? '',
                ['public-read/es/collection-items', 'public-read/es/events'],
                true,
            )))
            ->willReturnCallback(function (array $requests): array {
                return [($requests[0]['path'] ?? '') === 'public-read/es/collection-items'
                    ? $this->success([['id' => 7]])
                    : $this->success([['id' => 8]])];
            });

        $service = new BlockPrefetchService($client);
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
                    && ($requests[0]['path'] ?? '') === 'public-read/es/collection-items'
                    && ($requests[1]['path'] ?? '') === 'public/catalog/categories';
            }))
            ->willReturn([
                $this->success([['id' => 10, 'name' => 'Work']], ['pagination' => ['current_page' => 1]]),
                $this->success([['id' => 4, 'slug' => 'works', 'name' => 'Works']]),
            ]);

        $service = new BlockPrefetchService($client);
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
            ->with($this->callback(static fn (array $requests): bool => ($requests[0]['path'] ?? '') === 'public-read/es/events/201'))
            ->willReturn([$this->success([['id' => 201, 'title' => 'Prefetched event']])]);

        $service = new BlockPrefetchService($event);
        $result = $service->prefetch([[
            'block_key' => 'event_item_header',
            'block_data' => ['event_id' => 201],
        ]]);

        $this->assertTrue($result['0']['ok']);
        $this->assertSame('Prefetched event', $result['0']['data'][0]['title']);
    }

    public function testSeededDetailDoesNotIssueASecondRequest(): void
    {
        $event = $this->createMock(WebApiClientInterface::class);
        $event->expects($this->never())->method('multiGet');

        $service = new BlockPrefetchService($event);
        $result = $service->prefetchContext([
            ['block_key' => 'event_item_header', 'block_data' => ['event_slug' => 'festival-uno']],
        ], 'es', [
            'event_items' => [[
                'id' => 201,
                'slug' => 'festival-uno',
                'title' => 'Festival Uno',
            ]],
        ]);

        $this->assertTrue($result['block_prefetch']['0']['ok']);
        $this->assertSame('Festival Uno', $result['block_prefetch']['0']['data'][0]['title']);
    }

    public function testFormsAndBlocksShareTheInitialPrefetchBatch(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static function (array $requests): bool {
                $paths = array_map(static fn (array $request): string => (string) ($request['path'] ?? ''), $requests);

                return count($requests) === 2
                    && in_array('public-read/es/entries/news', $paths, true)
                    && in_array('public/es/forms/contact', $paths, true);
            }))
            ->willReturn([
                $this->success([['id' => 1]]),
                $this->success(['fields' => [['name' => 'email']]]),
            ]);

        $service = new BlockPrefetchService($client);
        $context = $service->prefetchContext([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
            ['block_key' => 'form_embed', 'block_config' => ['form_key' => 'contact']],
        ], 'es');

        $this->assertSame(['fields' => [['name' => 'email']],], $context['form_definitions']['contact']);
    }

    public function testContextExposesAllDynamicContentScopesForHtmlInvalidation(): void
    {
        $service = new BlockPrefetchService($this->createMock(WebApiClientInterface::class));

        $context = $service->prefetchContext([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'cartelera']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'museo']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
            ['block_key' => 'form_embed', 'block_config' => ['form_key' => 'contact']],
        ], 'es');

        $this->assertSame([
            'events',
            'event_types',
            'collection_items',
            'categories',
            'collections',
            'entries',
            'taxonomies',
            'forms',
        ], $context['cacheScopes']);
    }

    public function testIdenticalRequestsAreDeduplicatedButMappedToBothBlocks(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->once())
            ->method('multiGet')
            ->with($this->callback(static fn (array $requests): bool => count($requests) === 1))
            ->willReturn([$this->success([['id' => 1]])]);

        $service = new BlockPrefetchService($client);
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

        $service = new BlockPrefetchService($client);
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

        $service = new BlockPrefetchService($client);
        $result = $service->prefetch([[
            'block_key' => 'container',
            'children' => [[
                'block_key' => 'collection_timeline',
                'block_config' => ['collection_key' => 'archive'],
            ]],
        ]]);

        $this->assertSame(9, $result['0.0']['data'][0]['id']);
    }

    /**
     * Regression for docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md
     * §2.C: the prefetch pipeline builds its own `include=listing_content.*`
     * query independently of CollectionGridViewModel::entries()/
     * CollectionTimelineViewModel::entries()/CmsCollectionSource::fetch() —
     * if the two drift apart, the ViewModel's request misses the prefetch
     * cache and both a prefetch and a redundant live fetch happen. Pins the
     * exact sub-key set each block type must request, matching what each
     * consumer actually reads.
     */
    public function testCmsListBlocksRequestOnlyTheListingContentSubKeysEachOneConsumes(): void
    {
        $cases = [
            'collection_grid' => 'listing_content.fields',
            'collection_listing' => 'listing_content.image,listing_content.secondary_action,listing_content.rich_text,listing_content.video,listing_content.publication_date,listing_content.date_fields,listing_content.fields',
            'collection_timeline' => 'listing_content.publication_date,listing_content.documents',
        ];

        foreach ($cases as $blockKey => $expectedInclude) {
            $client = $this->createMock(WebApiClientInterface::class);
            $client->expects($this->once())
                ->method('multiGet')
                ->with($this->callback(static function (array $requests) use ($expectedInclude): bool {
                    return ($requests[0]['query']['include'] ?? null) === $expectedInclude;
                }))
                ->willReturn([$this->success([['id' => 1]])]);

            $service = new BlockPrefetchService($client);
            $result = $service->prefetch([[
                'block_key' => $blockKey,
                'block_config' => ['collection_key' => 'news'],
            ]]);

            $this->assertTrue($result['0']['ok'], "block_key={$blockKey}");
        }
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
