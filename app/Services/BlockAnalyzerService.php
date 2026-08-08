<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\BlockAnalyzerInterface;

class BlockAnalyzerService implements BlockAnalyzerInterface
{
    /**
     * Map of block types to their data requirements and extractors.
     * Each entry defines what resource type and fields are needed.
     *
     * @var array<string, array{resource_type: string, field_keys: array<int, string>, extractor: string, fields: array<int, string>}>
     */
    private const BLOCK_REQUIREMENTS = [
        'collection_grid' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['ids'],
            'extractor' => 'extractCollectionIds',
            'fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url', 'summary'],
        ],
        'collection_listing' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['ids'],
            'extractor' => 'extractCollectionIds',
            'fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url', 'summary'],
        ],
        'collection_timeline' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['ids'],
            'extractor' => 'extractCollectionIds',
            'fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url', 'period'],
        ],
        'event_item_header' => [
            'resource_type' => 'events',
            'field_keys' => ['id'],
            'extractor' => 'extractEventId',
            'fields' => ['id', 'uuid', 'title', 'slug', 'event_type', 'cover_file_id', 'cover_image'],
        ],
        'event_item_details' => [
            'resource_type' => 'events',
            'field_keys' => ['id'],
            'extractor' => 'extractEventId',
            'fields' => ['id', 'description', 'localized', 'translations'],
        ],
        'event_item_content' => [
            'resource_type' => 'events',
            'field_keys' => ['id'],
            'extractor' => 'extractEventId',
            'fields' => ['id', 'title', 'content', 'translations'],
        ],
        'event_item_gallery' => [
            'resource_type' => 'events',
            'field_keys' => ['id'],
            'extractor' => 'extractEventId',
            'fields' => ['id', 'gallery_file_ids', 'gallery_images'],
        ],
        'catalog_item_header' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['id'],
            'extractor' => 'extractCatalogItemId',
            'fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url', 'category_id'],
        ],
        'catalog_item_details' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['id'],
            'extractor' => 'extractCatalogItemId',
            'fields' => ['id', 'description', 'localized', 'translations'],
        ],
        'catalog_item_content' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['id'],
            'extractor' => 'extractCatalogItemId',
            'fields' => ['id', 'name', 'content', 'translations'],
        ],
        'catalog_item_gallery' => [
            'resource_type' => 'collection_items',
            'field_keys' => ['id'],
            'extractor' => 'extractCatalogItemId',
            'fields' => ['id', 'gallery_file_ids'],
        ],
    ];

    public function analyze(array $blocks, string $locale = 'es'): array
    {
        $requirements = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockType = $block['block_key'] ?? null;
            if ($blockType === null) {
                continue;
            }

            $blockData = $block['data'] ?? [];
            if (!is_array($blockData)) {
                continue;
            }

            $blockReqs = $this->getBlockRequirements($blockType, $blockData, $locale);
            if (empty($blockReqs)) {
                continue;
            }

            foreach ($blockReqs as $resourceType => $typeReqs) {
                if (!isset($requirements[$resourceType])) {
                    $requirements[$resourceType] = [
                        'ids' => [],
                        'slugs' => [],
                        'fields' => [],
                    ];
                }

                if (isset($typeReqs['ids']) && is_array($typeReqs['ids'])) {
                    $requirements[$resourceType]['ids'] = array_values(
                        array_unique(
                            array_merge($requirements[$resourceType]['ids'], $typeReqs['ids'])
                        )
                    );
                }

                if (isset($typeReqs['slugs']) && is_array($typeReqs['slugs'])) {
                    $requirements[$resourceType]['slugs'] = array_values(
                        array_unique(
                            array_merge($requirements[$resourceType]['slugs'], $typeReqs['slugs'])
                        )
                    );
                }

                if (isset($typeReqs['fields']) && is_array($typeReqs['fields'])) {
                    $requirements[$resourceType]['fields'] = array_values(
                        array_unique(
                            array_merge($requirements[$resourceType]['fields'], $typeReqs['fields'])
                        )
                    );
                }
            }
        }

        // Remove empty entries
        foreach ($requirements as $type => $reqs) {
            if (empty($reqs['ids']) && empty($reqs['slugs'])) {
                unset($requirements[$type]);
            }
        }

        return $requirements;
    }

    public function getBlockRequirements(string $blockType, array $blockData, string $locale = 'es'): array
    {
        if (!isset(self::BLOCK_REQUIREMENTS[$blockType])) {
            return [];
        }

        $config = self::BLOCK_REQUIREMENTS[$blockType];
        $resourceType = $config['resource_type'];
        $extractor = $config['extractor'];

        if (!method_exists($this, $extractor)) {
            return [];
        }

        $extracted = $this->$extractor($blockData);
        if (empty($extracted)) {
            return [];
        }

        return [
            $resourceType => array_merge(['fields' => $config['fields']], $extracted),
        ];
    }

    /**
     * @param array<string, mixed> $blockData
     * @return array<string, array<int, int|string>>
     */
    private function extractCollectionIds(array $blockData): array
    {
        $ids = [];

        // Standard field: array of collection item IDs
        if (isset($blockData['collection_item_ids']) && is_array($blockData['collection_item_ids'])) {
            foreach ($blockData['collection_item_ids'] as $id) {
                if (is_int($id) && $id > 0) {
                    $ids[] = $id;
                }
            }
        }

        // Alternative: single ID
        if (empty($ids) && isset($blockData['collection_item_id']) && is_int($blockData['collection_item_id']) && $blockData['collection_item_id'] > 0) {
            $ids[] = $blockData['collection_item_id'];
        }

        return empty($ids) ? [] : ['ids' => $ids];
    }

    /**
     * @param array<string, mixed> $blockData
     * @return array<string, array<int, int|string>>
     */
    private function extractEventId(array $blockData): array
    {
        if (isset($blockData['event_id']) && is_int($blockData['event_id']) && $blockData['event_id'] > 0) {
            return ['ids' => [$blockData['event_id']]];
        }

        if (isset($blockData['event_slug']) && is_string($blockData['event_slug'])) {
            return ['slugs' => [$blockData['event_slug']]];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $blockData
     * @return array<string, array<int, int|string>>
     */
    private function extractCatalogItemId(array $blockData): array
    {
        if (isset($blockData['collection_item_id']) && is_int($blockData['collection_item_id']) && $blockData['collection_item_id'] > 0) {
            return ['ids' => [$blockData['collection_item_id']]];
        }

        if (isset($blockData['collection_item_slug']) && is_string($blockData['collection_item_slug'])) {
            return ['slugs' => [$blockData['collection_item_slug']]];
        }

        return [];
    }
}
