<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Interfaces\BlockAnalyzerInterface;
use App\Services\BlockAnalyzerService;
use PHPUnit\Framework\TestCase;

class BlockAnalyzerServiceTest extends TestCase
{
    private BlockAnalyzerInterface $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new BlockAnalyzerService();
    }

    public function test_analyze_empty_blocks_array(): void
    {
        $result = $this->analyzer->analyze([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_analyze_blocks_without_block_key(): void
    {
        $blocks = [
            ['data' => ['foo' => 'bar']],
            ['block_key' => '', 'data' => []],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertEmpty($result);
    }

    public function test_analyze_collection_grid_extracts_ids(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => [
                    'collection_item_ids' => [1, 2, 3],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([1, 2, 3], $result['collection_items']['ids']);
        $this->assertNotEmpty($result['collection_items']['fields']);
    }

    public function test_analyze_collection_listing_extracts_ids(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_listing',
                'data' => [
                    'collection_item_ids' => [10, 20],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([10, 20], $result['collection_items']['ids']);
    }

    public function test_analyze_collection_timeline_extracts_ids(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_timeline',
                'data' => [
                    'collection_item_ids' => [5],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([5], $result['collection_items']['ids']);
        $this->assertContains('period', $result['collection_items']['fields']);
    }

    public function test_analyze_event_item_header_by_id(): void
    {
        $blocks = [
            [
                'block_key' => 'event_item_header',
                'data' => [
                    'event_id' => 42,
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('events', $result);
        $this->assertEquals([42], $result['events']['ids']);
        $this->assertContains('title', $result['events']['fields']);
    }

    public function test_analyze_event_item_header_by_slug(): void
    {
        $blocks = [
            [
                'block_key' => 'event_item_header',
                'data' => [
                    'event_slug' => 'festival-de-luz',
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('events', $result);
        $this->assertEquals(['festival-de-luz'], $result['events']['slugs']);
    }

    public function test_analyze_catalog_item_header_by_id(): void
    {
        $blocks = [
            [
                'block_key' => 'catalog_item_header',
                'data' => [
                    'collection_item_id' => 99,
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([99], $result['collection_items']['ids']);
        $this->assertContains('name', $result['collection_items']['fields']);
    }

    public function test_analyze_catalog_item_header_by_slug(): void
    {
        $blocks = [
            [
                'block_key' => 'catalog_item_header',
                'data' => [
                    'collection_item_slug' => 'payaso',
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals(['payaso'], $result['collection_items']['slugs']);
    }

    public function test_analyze_merges_multiple_blocks_of_same_type(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => ['collection_item_ids' => [1, 2]],
            ],
            [
                'block_key' => 'collection_listing',
                'data' => ['collection_item_ids' => [2, 3, 4]],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], $result['collection_items']['ids']);
    }

    public function test_analyze_deduplicates_ids(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => ['collection_item_ids' => [1, 2, 2, 3, 1]],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEqualsCanonicalizing([1, 2, 3], $result['collection_items']['ids']);
    }

    public function test_analyze_merges_fields_from_multiple_blocks(): void
    {
        $blocks = [
            [
                'block_key' => 'event_item_header',
                'data' => ['event_id' => 1],
            ],
            [
                'block_key' => 'event_item_details',
                'data' => ['event_id' => 1],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('events', $result);
        $this->assertContains('title', $result['events']['fields']);
        $this->assertContains('description', $result['events']['fields']);
    }

    public function test_analyze_ignores_blocks_with_no_extractable_data(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => [
                    'collection_item_ids' => [],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertEmpty($result);
    }

    public function test_analyze_ignores_unknown_block_types(): void
    {
        $blocks = [
            [
                'block_key' => 'unknown_block_type',
                'data' => ['foo' => 'bar'],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertEmpty($result);
    }

    public function test_analyze_ignores_invalid_ids(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => [
                    'collection_item_ids' => [1, 'invalid', 0, -5, 2],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEqualsCanonicalizing([1, 2], $result['collection_items']['ids']);
    }

    public function test_analyze_ignores_non_array_data(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => 'not_an_array',
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertEmpty($result);
    }

    public function test_analyze_ignores_non_array_blocks(): void
    {
        $blocks = [
            'not_an_array',
            null,
            123,
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertEmpty($result);
    }

    public function test_get_block_requirements_returns_empty_for_unknown_type(): void
    {
        $result = $this->analyzer->getBlockRequirements('unknown_type', []);

        $this->assertEmpty($result);
    }

    public function test_get_block_requirements_returns_empty_when_no_data_extracted(): void
    {
        $result = $this->analyzer->getBlockRequirements('collection_grid', []);

        $this->assertEmpty($result);
    }

    public function test_get_block_requirements_collection_grid(): void
    {
        $blockData = [
            'collection_item_ids' => [1, 2, 3],
        ];

        $result = $this->analyzer->getBlockRequirements('collection_grid', $blockData);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([1, 2, 3], $result['collection_items']['ids']);
        $this->assertContains('id', $result['collection_items']['fields']);
        $this->assertContains('cover_url', $result['collection_items']['fields']);
    }

    public function test_get_block_requirements_event_item_header(): void
    {
        $blockData = ['event_id' => 42];

        $result = $this->analyzer->getBlockRequirements('event_item_header', $blockData);

        $this->assertArrayHasKey('events', $result);
        $this->assertEquals([42], $result['events']['ids']);
        $this->assertContains('title', $result['events']['fields']);
    }

    public function test_get_block_requirements_catalog_item_details(): void
    {
        $blockData = ['collection_item_id' => 10];

        $result = $this->analyzer->getBlockRequirements('catalog_item_details', $blockData);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([10], $result['collection_items']['ids']);
        $this->assertContains('description', $result['collection_items']['fields']);
    }

    public function test_get_block_requirements_respects_locale_parameter(): void
    {
        $blockData = ['event_id' => 1];

        $result = $this->analyzer->getBlockRequirements('event_item_header', $blockData, 'en');

        $this->assertArrayHasKey('events', $result);
        $this->assertEquals([1], $result['events']['ids']);
    }

    public function test_collection_item_single_id_extraction(): void
    {
        $blocks = [
            [
                'block_key' => 'collection_grid',
                'data' => [
                    'collection_item_id' => 5,
                ],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertArrayHasKey('collection_items', $result);
        $this->assertEquals([5], $result['collection_items']['ids']);
    }

    public function test_analyze_complex_page_with_mixed_blocks(): void
    {
        $blocks = [
            ['block_key' => 'hero_slider', 'data' => []],
            [
                'block_key' => 'collection_grid',
                'data' => ['collection_item_ids' => [1, 2]],
            ],
            ['block_key' => 'text_content', 'data' => ['content' => 'Lorem ipsum']],
            [
                'block_key' => 'event_item_header',
                'data' => ['event_id' => 10],
            ],
            [
                'block_key' => 'event_item_details',
                'data' => ['event_id' => 10],
            ],
        ];

        $result = $this->analyzer->analyze($blocks);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('collection_items', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertEquals([1, 2], $result['collection_items']['ids']);
        $this->assertEquals([10], $result['events']['ids']);
    }
}
