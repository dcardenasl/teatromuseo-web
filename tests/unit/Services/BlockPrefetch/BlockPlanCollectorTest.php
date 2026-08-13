<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BlockPrefetch;

use App\Services\BlockPrefetch\BlockPlanCollector;
use App\Services\BlockPrefetch\BlockResultMaterializer;
use App\Services\BlockPrefetch\RequestQueryReader;
use PHPUnit\Framework\TestCase;

final class BlockPlanCollectorTest extends TestCase
{
    private BlockPlanCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new BlockPlanCollector(new BlockResultMaterializer(new RequestQueryReader()));
    }

    public function testCollectSkipsStaticBlocksAndKeysPlansByPath(): void
    {
        $plans = $this->collector->collect([
            ['block_key' => 'hero_banner', 'block_config' => []],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
        ], 'es');

        $this->assertArrayNotHasKey('0', $plans);
        $this->assertArrayHasKey('1', $plans);
        $this->assertSame('list', $plans['1']['kind']);
        $this->assertSame('cms_collection', $plans['1']['source_type']);
    }

    public function testCollectWalksNestedChildrenWithDottedPaths(): void
    {
        $plans = $this->collector->collect([[
            'block_key' => 'container',
            'children' => [[
                'block_key' => 'collection_timeline',
                'block_config' => ['collection_key' => 'archive'],
            ]],
        ]], 'es');

        $this->assertArrayHasKey('0.0', $plans);
        $this->assertSame('collection_timeline', $plans['0.0']['block_key']);
    }

    public function testDetailBlockKindIsDetailNotList(): void
    {
        $plans = $this->collector->collect([
            ['block_key' => 'event_item_header', 'block_data' => ['event_id' => 5]],
        ], 'es');

        $this->assertSame('detail', $plans['0']['kind']);
        $this->assertSame('event_items', $plans['0']['source_type']);
    }

    public function testFormKeysDefaultToContactAndDeduplicateAcrossNesting(): void
    {
        $keys = $this->collector->formKeys([
            ['block_key' => 'form_embed', 'block_config' => []],
            [
                'block_key' => 'container',
                'children' => [['block_key' => 'form_embed', 'block_config' => ['form_key' => 'newsletter']]],
            ],
        ]);

        $this->assertSame(['contact', 'newsletter'], $keys);
    }

    public function testCacheScopesAggregateBySourceTypeAndDeduplicate(): void
    {
        $scopes = $this->collector->cacheScopes([
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'cartelera']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'museo']],
            ['block_key' => 'collection_grid', 'block_config' => ['collection_key' => 'news']],
            ['block_key' => 'form_embed', 'block_config' => ['form_key' => 'contact']],
        ]);

        $this->assertSame([
            'events',
            'event_types',
            'collection_items',
            'categories',
            'collections',
            'entries',
            'taxonomies',
            'forms',
        ], $scopes);
    }

    public function testResolveSourceTypeAutoDetectsFromKnownCollectionKeys(): void
    {
        $this->assertSame('event_items', $this->collector->resolveSourceType(['collection_key' => 'cartelera']));
        $this->assertSame('catalog_items', $this->collector->resolveSourceType(['collection_key' => 'museo']));
        $this->assertSame('cms_collection', $this->collector->resolveSourceType(['collection_key' => 'news']));
    }

    public function testResolveSourceTypeExplicitOverrideWins(): void
    {
        $this->assertSame(
            'catalog_items',
            $this->collector->resolveSourceType(['collection_key' => 'cartelera', 'source_type' => 'catalog_items']),
        );
    }

    public function testResolveSourceTypeUsesBlockKeyPrefixForDetailBlocksWithoutAnExplicitSourceType(): void
    {
        $this->assertSame('event_items', $this->collector->resolveSourceType([], 'event_item_header'));
        $this->assertSame('catalog_items', $this->collector->resolveSourceType([], 'catalog_item_header'));
    }

    public function testPayloadMergesAndDecodesEveryConfigKeyInPrecedenceOrder(): void
    {
        $payload = $this->collector->payload([
            'data' => ['a' => 1],
            'block_data' => json_encode(['b' => 2]),
            'config' => ['a' => 'overwritten-by-block_config-not-data'],
            'block_config' => ['c' => 3],
        ]);

        $this->assertSame(['a' => 'overwritten-by-block_config-not-data', 'b' => 2, 'c' => 3], $payload);
    }
}
