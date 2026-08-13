<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Libraries\WebApiClientInterface;
use App\Services\BlockPrefetch\BlockRequestPlanner;
use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\ListQueryBuilder;
use App\Services\BlockPrefetch\PrefetchRequestQueue;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

final class BlockRequestPlannerTest extends TestCase
{
    private BlockResultMaterializer $results;

    protected function setUp(): void
    {
        parent::setUp();
        $this->results = new BlockResultMaterializer(new RequestQueryReader());
    }

    public function testDetailBlockWithIdQueuesALookupByEndpointAndId(): void
    {
        $planner = $this->planner(['event' => $this->createMock(WebApiClientInterface::class)]);
        $plan = $this->detailPlan('event_item_header', ['event_id' => 42], 'event_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame(0, $plan['main_index']);
        $this->assertSame('public-read/es/events/42', $queue->all()[0]['path']);
        $this->assertSame('events', $queue->all()[0]['scope']);
    }

    public function testDetailBlockWithSlugUsesTheSlugReference(): void
    {
        $planner = $this->planner(['catalog' => $this->createMock(WebApiClientInterface::class)]);
        $plan = $this->detailPlan('catalog_item_header', ['collection_item_slug' => 'jarron-azul'], 'catalog_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame('public-read/es/collection-items/jarron-azul', $queue->all()[0]['path']);
    }

    public function testDetailBlockWithoutIdOrSlugFailsWith422(): void
    {
        $planner = $this->planner([]);
        $plan = $this->detailPlan('event_item_header', [], 'event_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame(0, $queue->count());
        $this->assertSame(422, $plan['result']['status']);
    }

    public function testSeededItemSkipsTheRequestEntirely(): void
    {
        $client = $this->createMock(WebApiClientInterface::class);
        $client->expects($this->never())->method('multiGet');
        $planner = $this->planner(['event' => $client]);
        $plan = $this->detailPlan('event_item_header', ['event_slug' => 'festival-uno'], 'event_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue, [
            'event_items' => [['id' => 1, 'slug' => 'festival-uno', 'title' => 'Festival Uno']],
        ]);

        $this->assertSame(0, $queue->count());
        $this->assertSame('Festival Uno', $plan['seeded_item']['title']);
    }

    public function testUnavailableClientFailsWith503(): void
    {
        $planner = $this->planner([]);
        $plan = $this->detailPlan('event_item_header', ['event_id' => 1], 'event_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame(503, $plan['result']['status']);
    }

    public function testInvalidSourceTypeFailsWith422(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', [], 'not-a-real-source-type');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame(422, $plan['result']['status']);
    }

    public function testCmsCollectionResolvesCollectionIdBeforeTheListingRequest(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', ['collection_id' => 5, 'collection_key' => 'news'], 'cms_collection');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame('public/es/collections', $queue->all()[0]['path']);
        $this->assertSame('public-read/es/entries/news', $queue->all()[1]['path']);
    }

    public function testCmsCollectionWithoutAResolvedKeyStopsBeforeTheListingRequest(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', ['collection_id' => 5], 'cms_collection');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame(1, $queue->count());
        $this->assertNull($plan['main_index']);
    }

    public function testCatalogItemsWithACategoryFilterQueuesTheCategoryDependencyFirst(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', ['category' => 'pintura'], 'catalog_items');
        $queue = $this->queue();

        $planner->planInitial($plan, 'es', $queue);

        $this->assertSame('public/catalog/categories', $queue->all()[0]['path']);
        $this->assertNull($plan['main_index']);
    }

    public function testAddListRequestsFailsWith422WhenCmsCollectionKeyIsMissing(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', [], 'cms_collection');
        $queue = $this->queue();

        $planner->addListRequests($plan, 'es', $queue);

        $this->assertSame(422, $plan['result']['status']);
    }

    public function testCollectionListingQueuesCategoryAndTagFacetsForCmsCollections(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_listing', ['collection_key' => 'news', 'show_tags' => true], 'cms_collection');
        $queue = $this->queue();

        $planner->addListRequests($plan, 'es', $queue);

        $paths = array_column($queue->all(), 'path');
        $this->assertContains('public/es/categories/news', $paths);
        $this->assertContains('public/es/tags/news', $paths);
    }

    public function testCollectionListingQueuesEventTypesAsTheTagFacet(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_listing', [], 'event_items');
        $queue = $this->queue();

        $planner->addListRequests($plan, 'es', $queue);

        $this->assertContains('public/events/types', array_column($queue->all(), 'path'));
    }

    public function testGridBlocksNeverRequestFacetsEvenWhenShowFlagsAreTrue(): void
    {
        $planner = $this->planner([]);
        $plan = $this->listPlan('collection_grid', ['show_categories' => true, 'show_tags' => true, 'collection_key' => 'news'], 'cms_collection');
        $queue = $this->queue();

        $planner->addListRequests($plan, 'es', $queue);

        $this->assertSame(1, $queue->count());
    }

    private function queue(): PrefetchRequestQueue
    {
        return new PrefetchRequestQueue('es', new RequestQueryReader());
    }

    /** @param array<string, WebApiClientInterface> $clients */
    private function planner(array $clients): BlockRequestPlanner
    {
        return new BlockRequestPlanner($clients, new ListQueryBuilder(new RequestQueryReader()), $this->results);
    }

    /** @param array<string, mixed> $payload */
    private function detailPlan(string $blockKey, array $payload, string $sourceType): array
    {
        return [
            'block_key' => $blockKey,
            'payload' => $payload,
            'source_type' => $sourceType,
            'kind' => 'detail',
            'main_index' => null,
            'main_query' => [],
            'facet_indexes' => [],
            'collection_index' => null,
            'dependency_indexes' => [],
            'result' => $this->results->emptyResult(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function listPlan(string $blockKey, array $payload, string $sourceType): array
    {
        $plan = $this->detailPlan($blockKey, $payload, $sourceType);
        $plan['kind'] = 'list';
        $plan['collection_key'] = (string) ($payload['collection_key'] ?? '');

        return $plan;
    }
}
