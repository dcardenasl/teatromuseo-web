<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

final class BlockResultMaterializerTest extends TestCase
{
    private BlockResultMaterializer $materializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->materializer = new BlockResultMaterializer(new RequestQueryReader());
    }

    public function testListPlanNormalizesMissingPagination(): void
    {
        $plan = $this->basePlan(kind: 'list', mainIndex: 0, query: ['page' => 1, 'per_page' => 12]);
        $responses = [0 => [
            'ok' => true,
            'status' => 200,
            'data' => [['id' => 1], ['id' => 2]],
            'meta' => [],
            'messages' => [],
        ]];

        $result = $this->materializer->materialize($plan, $responses);

        $this->assertTrue($result['ok']);
        $this->assertSame([['id' => 1], ['id' => 2]], $result['data']);
        $this->assertSame([
            'total' => 2,
            'total_items' => 2,
            'page' => 1,
            'current_page' => 1,
            'per_page' => 12,
            'total_pages' => 1,
            'has_next_page' => false,
            'has_previous_page' => false,
        ], $result['meta']['pagination']);
    }

    public function testDetailPlanCollapsesToFirstItem(): void
    {
        $plan = $this->basePlan(kind: 'detail', mainIndex: 0);
        $responses = [0 => [
            'ok' => true,
            'status' => 200,
            'data' => [['id' => 9, 'title' => 'A'], ['id' => 10, 'title' => 'B']],
            'meta' => [],
            'messages' => [],
        ]];

        $result = $this->materializer->materialize($plan, $responses);

        $this->assertSame([['id' => 9, 'title' => 'A']], $result['data']);
    }

    public function testSeededItemSkipsResponseLookupEntirely(): void
    {
        $plan = $this->basePlan(kind: 'detail', mainIndex: null);
        $plan['seeded_item'] = ['id' => 42, 'title' => 'Seeded'];

        $result = $this->materializer->materialize($plan, []);

        $this->assertTrue($result['ok']);
        $this->assertSame([['id' => 42, 'title' => 'Seeded']], $result['data']);
    }

    public function testMissingMainKeepsExplicitEmptyEnvelope(): void
    {
        $plan = $this->basePlan(kind: 'list', mainIndex: null);

        $result = $this->materializer->materialize($plan, []);

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['data']);
        $this->assertSame('unavailable', $result['instance']['source']);
    }

    public function testFacetIndexesArePopulatedFromTheirOwnResponses(): void
    {
        $plan = $this->basePlan(kind: 'list', mainIndex: 0);
        $plan['facet_indexes'] = ['categories' => 1];
        $responses = [
            0 => ['ok' => true, 'status' => 200, 'data' => [['id' => 1]], 'meta' => [], 'messages' => []],
            1 => ['ok' => true, 'status' => 200, 'data' => [['id' => 4, 'name' => 'Works']], 'meta' => [], 'messages' => []],
        ];

        $result = $this->materializer->materialize($plan, $responses);

        $this->assertSame([['id' => 4, 'name' => 'Works']], $result['facets']['categories']);
    }

    public function testInstanceMetadataDerivesPageLimitAndOrderFromTheMainQuery(): void
    {
        $plan = $this->basePlan(kind: 'list', mainIndex: 0, query: [
            'page' => 2,
            'per_page' => 6,
            'sort' => 'title',
            'order_direction' => 'asc',
            'category' => 'pintura',
        ]);
        $responses = [0 => ['ok' => true, 'status' => 200, 'data' => [], 'meta' => [], 'messages' => []]];

        $instance = $this->materializer->materialize($plan, $responses)['instance'];

        $this->assertSame(2, $instance['page']);
        $this->assertSame(6, $instance['limit']);
        $this->assertSame(['sort' => 'title', 'direction' => 'asc'], $instance['order']);
        $this->assertSame('pintura', $instance['filters']['category']);
        $this->assertFalse($instance['preview']);
        $this->assertSame('fresh', $instance['source']);
    }

    public function testEmptyAndFailedResultShapes(): void
    {
        $empty = $this->materializer->emptyResult();
        $this->assertFalse($empty['ok']);
        $this->assertSame(['categories' => [], 'tags' => []], $empty['facets']);

        $failed = $this->materializer->failedResult(422, 'Invalid config.');
        $this->assertSame(422, $failed['status']);
        $this->assertSame(['Invalid config.'], $failed['messages']);
    }

    /** @return array<string, mixed> */
    private function basePlan(string $kind, ?int $mainIndex, array $query = []): array
    {
        return [
            'block' => [],
            'block_key' => 'collection_grid',
            'block_path' => '0',
            'locale' => 'es',
            'payload' => [],
            'source_type' => 'cms_collection',
            'kind' => $kind,
            'main_index' => $mainIndex,
            'main_query' => $query,
            'facet_indexes' => [],
            'collection_index' => null,
            'dependency_indexes' => [],
            'result' => $this->materializer->emptyResult(),
        ];
    }
}
