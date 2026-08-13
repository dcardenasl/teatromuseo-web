<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Services\BlockPrefetch\BlockDependencyResolver;
use App\Services\BlockPrefetch\BlockRequestPlanner;
use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\ListQueryBuilder;
use App\Services\BlockPrefetch\PrefetchRequestQueue;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

final class BlockDependencyResolverTest extends TestCase
{
    private BlockDependencyResolver $resolver;
    private BlockResultMaterializer $results;

    protected function setUp(): void
    {
        parent::setUp();
        $reader = new RequestQueryReader();
        $this->results = new BlockResultMaterializer($reader);
        $this->resolver = new BlockDependencyResolver(
            new BlockRequestPlanner([], new ListQueryBuilder($reader), $this->results),
            new ListQueryBuilder($reader),
            $this->results,
        );
    }

    public function testResolvedCollectionUnblocksTheListingRequest(): void
    {
        $plans = ['0' => $this->cmsCollectionPlan(collectionIndex: 0, collectionKey: '')];
        $responses = [0 => [
            'ok' => true,
            'status' => 200,
            'data' => [['id' => 5, 'collection_key' => 'news']],
            'meta' => [],
            'messages' => [],
        ]];
        $queue = $this->queue();

        $this->resolver->resolve($plans, $responses, 'es', $queue);

        $this->assertSame('news', $plans['0']['collection_key']);
        $this->assertSame('public-read/es/entries/news', $queue->all()[0]['path']);
        $this->assertNotSame(404, $plans['0']['result']['status']);
    }

    public function testUnresolvableCollectionFailsWith404AndQueuesNothingMore(): void
    {
        $plans = ['0' => $this->cmsCollectionPlan(collectionIndex: 0, collectionKey: '')];
        $responses = [0 => ['ok' => true, 'status' => 200, 'data' => [], 'meta' => [], 'messages' => []]];
        $queue = $this->queue();

        $this->resolver->resolve($plans, $responses, 'es', $queue);

        $this->assertSame(404, $plans['0']['result']['status']);
        $this->assertSame(0, $queue->count());
    }

    public function testCategorySlugResolvesToAnIdBeforeQueuingTheListingRequest(): void
    {
        $plan = $this->catalogPlan(category: 'pintura', categoryDependencyIndex: 0);
        $plans = ['0' => $plan];
        $responses = [0 => [
            'ok' => true,
            'status' => 200,
            'data' => [['id' => 9, 'slug' => 'pintura']],
            'meta' => [],
            'messages' => [],
        ]];
        $queue = $this->queue();

        $this->resolver->resolve($plans, $responses, 'es', $queue);

        $this->assertSame(9, $plans['0']['category_id']);
        $this->assertSame('public-read/es/collection-items', $queue->all()[0]['path']);
        $this->assertSame(9, $queue->all()[0]['query']['category_id']);
    }

    public function testPlansThatAlreadyHaveAMainIndexAreLeftUntouched(): void
    {
        $plan = $this->cmsCollectionPlan(collectionIndex: null, collectionKey: 'news');
        $plan['main_index'] = 3;
        $plans = ['0' => $plan];
        $queue = $this->queue();

        $this->resolver->resolve($plans, [], 'es', $queue);

        $this->assertSame(0, $queue->count());
        $this->assertSame(3, $plans['0']['main_index']);
    }

    public function testDetailPlansAreSkipped(): void
    {
        $plan = $this->cmsCollectionPlan(collectionIndex: null, collectionKey: '');
        $plan['kind'] = 'detail';
        $plans = ['0' => $plan];
        $queue = $this->queue();

        $this->resolver->resolve($plans, [], 'es', $queue);

        $this->assertSame(0, $queue->count());
    }

    private function queue(): PrefetchRequestQueue
    {
        return new PrefetchRequestQueue('es', new RequestQueryReader());
    }

    /** @return array<string, mixed> */
    private function cmsCollectionPlan(?int $collectionIndex, string $collectionKey): array
    {
        return [
            'block_key' => 'collection_grid',
            'payload' => [],
            'source_type' => 'cms_collection',
            'kind' => 'list',
            'main_index' => null,
            'main_query' => [],
            'facet_indexes' => [],
            'collection_index' => $collectionIndex,
            'collection_id' => 5,
            'collection_key' => $collectionKey,
            'dependency_indexes' => [],
            'result' => $this->results->emptyResult(),
        ];
    }

    /** @return array<string, mixed> */
    private function catalogPlan(string $category, ?int $categoryDependencyIndex): array
    {
        return [
            'block_key' => 'collection_grid',
            'payload' => ['category' => $category],
            'source_type' => 'catalog_items',
            'kind' => 'list',
            'main_index' => null,
            'main_query' => [],
            'facet_indexes' => [],
            'collection_index' => null,
            'dependency_indexes' => $categoryDependencyIndex === null ? [] : ['categories' => $categoryDependencyIndex],
            'result' => $this->results->emptyResult(),
        ];
    }
}
