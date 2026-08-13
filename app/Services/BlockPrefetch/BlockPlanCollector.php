<?php

declare(strict_types=1);

namespace App\Services\BlockPrefetch;

/**
 * Walks a page's block tree (including nested `children`) and identifies
 * what needs prefetching: dynamic list/detail blocks, `form_embed` form
 * keys, and the cache scopes the composed page depends on. Produces plain
 * plan data — it never issues a request itself.
 */
final class BlockPlanCollector
{
    private const LIST_BLOCKS = [
        'collection_grid',
        'collection_listing',
        'collection_timeline',
    ];

    private const DETAIL_PREFIXES = [
        'event_item_',
        'catalog_item_',
    ];

    public function __construct(private readonly BlockResultMaterializer $results)
    {
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<int|string, array<string, mixed>> Plans keyed by block path (e.g. "0", "2.1").
     */
    public function collect(array $blocks, string $locale): array
    {
        return $this->walkForPlans($blocks, '', $locale);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<string>
     */
    public function formKeys(array $blocks): array
    {
        /** @var array<string, true> $keys */
        $keys = [];
        $this->walkForFormKeys($blocks, $keys);

        return array_keys($keys);
    }

    /**
     * Derive the public data dependencies of a composed block tree. These
     * scopes are attached to the HTML registry so non-homepage variants are
     * invalidated together with the API data they embed.
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<string>
     */
    public function cacheScopes(array $blocks): array
    {
        $scopes = [];
        $this->walkForCacheScopes($blocks, $scopes);

        return array_values(array_unique($scopes));
    }

    public function isDynamicBlock(string $blockKey): bool
    {
        return in_array($blockKey, self::LIST_BLOCKS, true)
            || str_starts_with($blockKey, self::DETAIL_PREFIXES[0])
            || str_starts_with($blockKey, self::DETAIL_PREFIXES[1]);
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    public function payload(array $block): array
    {
        $payload = [];
        foreach (['data', 'block_data', 'config', 'block_config'] as $key) {
            $value = $block[$key] ?? [];
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }
            if (is_array($value)) {
                $payload = array_merge($payload, $value);
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public function resolveSourceType(array $payload, string $blockKey = ''): string
    {
        $sourceType = strtolower(trim((string) ($payload['source_type'] ?? 'auto')));
        if ($sourceType !== 'auto') {
            return $sourceType;
        }

        if (str_starts_with($blockKey, 'event_item_')) {
            return 'event_items';
        }
        if (str_starts_with($blockKey, 'catalog_item_')) {
            return 'catalog_items';
        }

        $collectionKey = strtolower(trim((string) ($payload['collection_key'] ?? '')));
        $resolved = match ($collectionKey) {
            'cartelera', 'events', 'eventos' => 'event_items',
            'museo', 'catalogo', 'catalog', 'fichas', 'collection_items' => 'catalog_items',
            default => 'cms_collection',
        };

        if ($collectionKey !== '' && $resolved === 'cms_collection') {
            log_message('debug', sprintf(
                '[BlockPlanCollector] source_type=auto defaulted to CMS for collection_key "%s".',
                $collectionKey,
            ));
        }

        return $resolved;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<int|string, array<string, mixed>>
     */
    private function walkForPlans(array $blocks, string $parentPath, string $locale): array
    {
        $plans = [];
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $path = $parentPath === '' ? (string) $index : $parentPath . '.' . $index;
            $blockKey = (string) ($block['block_key'] ?? '');
            if ($this->isDynamicBlock($blockKey)) {
                $plans[$path] = $this->basePlan($block, $blockKey, $path, $locale);
            }

            $children = $block['children'] ?? [];
            if (is_array($children)) {
                $childBlocks = array_values(array_filter(
                    $children,
                    static fn (mixed $child): bool => is_array($child),
                ));
                $plans += $this->walkForPlans($childBlocks, $path, $locale);
            }
        }

        return $plans;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, true> $keys
     * @param-out array<string, true> $keys
     */
    private function walkForFormKeys(array $blocks, array &$keys): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['block_key'] ?? '') === 'form_embed') {
                $config = is_array($block['block_config'] ?? null) ? $block['block_config'] : [];
                $formKey = trim((string) ($config['form_key'] ?? 'contact'));
                if ($formKey !== '') {
                    $keys[$formKey] = true;
                }
            }

            $children = $block['children'] ?? [];
            if (is_array($children)) {
                $childBlocks = array_values(array_filter(
                    $children,
                    static fn (mixed $child): bool => is_array($child),
                ));
                $this->walkForFormKeys($childBlocks, $keys);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string> $scopes
     * @param-out list<string> $scopes
     */
    private function walkForCacheScopes(array $blocks, array &$scopes): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockKey = (string) ($block['block_key'] ?? '');
            if ($blockKey === 'form_embed') {
                $scopes[] = 'forms';
            }
            if ($this->isDynamicBlock($blockKey)) {
                $sourceType = $this->resolveSourceType($this->payload($block), $blockKey);
                $scopes = array_merge($scopes, match ($sourceType) {
                    'event_items' => ['events', 'event_types'],
                    'catalog_items' => ['collection_items', 'categories'],
                    'cms_collection' => ['collections', 'entries', 'taxonomies'],
                    default => [],
                });
            }

            $children = $block['children'] ?? [];
            if (is_array($children)) {
                $childBlocks = array_values(array_filter(
                    $children,
                    static fn (mixed $child): bool => is_array($child),
                ));
                $this->walkForCacheScopes($childBlocks, $scopes);
            }
        }
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function basePlan(array $block, string $blockKey, string $path, string $locale): array
    {
        $payload = $this->payload($block);
        $sourceType = $this->resolveSourceType($payload, $blockKey);

        return [
            'block' => $block,
            'block_key' => $blockKey,
            'block_path' => $path,
            'locale' => $locale,
            'payload' => $payload,
            'source_type' => $sourceType,
            'kind' => in_array($blockKey, self::LIST_BLOCKS, true) ? 'list' : 'detail',
            'main_index' => null,
            'main_query' => [],
            'facet_indexes' => [],
            'collection_index' => null,
            'dependency_indexes' => [],
            'result' => $this->results->emptyResult(),
        ];
    }
}
