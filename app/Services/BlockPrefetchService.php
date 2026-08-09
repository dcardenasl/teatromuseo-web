<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\BlockAnalyzerInterface;
use App\Interfaces\SmartPrefetchInterface;
use App\Libraries\WebApiClientInterface;

/**
 * Collects list requests for dynamic blocks before rendering starts.
 *
 * A page can contain several independent collection grids/timelines. Keeping
 * the request collection here lets WebApiClient::multiGet() execute each
 * domain's misses together, while the renderer still receives one result per
 * block path and preserves the existing ViewModel formatting.
 */
final class BlockPrefetchService
{
    /** @var array<string, WebApiClientInterface> */
    private array $clients = [];

    private ?BlockAnalyzerInterface $analyzer;

    private ?SmartPrefetchInterface $smartPrefetch;

    /**
     * @param array<string, WebApiClientInterface> $clients
     */
    public function __construct(
        array $clients,
        ?BlockAnalyzerInterface $analyzer = null,
        ?SmartPrefetchInterface $smartPrefetch = null,
    ) {
        $this->analyzer = $analyzer;
        $this->smartPrefetch = $smartPrefetch;

        foreach ($clients as $name => $client) {
            if ($client instanceof WebApiClientInterface) {
                $this->clients[$name] = $client;
            }
        }
    }

    /**
     * Resolve every dynamic block requirement through the page's single
     * prefetch boundary. List requests remain keyed by block path, while
     * detail resources are exposed by type for BlockRenderer's item bridge.
     *
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>
     */
    public function prefetchContext(array $blocks, string $locale = 'es'): array
    {
        $context = [
            'block_prefetch' => $this->prefetch($blocks, $locale),
        ];

        if ($this->analyzer === null || $this->smartPrefetch === null) {
            return $context;
        }

        $requirements = $this->analyzer->analyze($blocks, $locale);
        if ($requirements === []) {
            return $context;
        }

        try {
            return array_merge(
                $context,
                $this->smartPrefetch->prefetch($requirements, $locale),
            );
        } catch (\Throwable) {
            return $context;
        }
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array<string, array{data: list<array<string, mixed>>, meta: array<string, mixed>}>
     */
    public function prefetch(array $blocks, string $locale = 'es'): array
    {
        $requestsByClient = [];
        $requestMap = [];
        $this->collectRequests($blocks, '', $locale, $requestsByClient, $requestMap);

        if ($requestsByClient === []) {
            return [];
        }

        $results = [];
        foreach ($requestsByClient as $clientKey => $requests) {
            $client = $this->clients[$clientKey] ?? null;
            if (! $client instanceof WebApiClientInterface) {
                continue;
            }

            foreach ($client->multiGet($requests) as $index => $response) {
                $blockPath = $requestMap[$clientKey][$index] ?? null;
                if ($blockPath === null || ! ($response['ok'] ?? false)) {
                    continue;
                }

                $data = $response['data'] ?? [];
                if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
                    $data = $data['data'];
                }

                if (! is_array($data)) {
                    continue;
                }

                $items = [];
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }

                $results[$blockPath] = [
                    'data' => $items,
                    'meta' => is_array($response['meta'] ?? null) ? $response['meta'] : [],
                ];
            }
        }

        return $results;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<string, list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}>> $requestsByClient
     * @param array<string, list<string>> $requestMap
     */
    private function collectRequests(
        array $blocks,
        string $parentPath,
        string $locale,
        array &$requestsByClient,
        array &$requestMap,
    ): void {
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $path = $parentPath === '' ? (string) $index : $parentPath . '.' . $index;
            $plan = $this->planForBlock($block, $locale);
            if ($plan !== null) {
                $clientKey = $plan['client'];
                $requestsByClient[$clientKey][] = [
                    'path' => $plan['path'],
                    'query' => $plan['query'],
                    'cacheTtl' => 180,
                    'scope' => $plan['scope'],
                ];
                $requestMap[$clientKey][] = $path;
            }

            $children = $block['children'] ?? [];
            if (is_array($children)) {
                $childBlocks = array_values(array_filter(
                    $children,
                    static fn (mixed $child): bool => is_array($child),
                ));
                $this->collectRequests($childBlocks, $path, $locale, $requestsByClient, $requestMap);
            }
        }
    }

    /**
     * @param array<string, mixed> $block
     * @return array{client: string, path: string, query: array<string, mixed>, scope: string}|null
     */
    private function planForBlock(array $block, string $locale): ?array
    {
        $blockKey = (string) ($block['block_key'] ?? '');
        if (! in_array($blockKey, ['collection_grid', 'collection_timeline'], true)) {
            return null;
        }

        $payload = [];
        foreach (['block_data', 'block_config'] as $key) {
            $value = $block[$key] ?? [];
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }
            if (is_array($value)) {
                $payload = array_merge($payload, $value);
            }
        }

        $collectionKey = trim((string) ($payload['collection_key'] ?? ''));
        $sourceType = strtolower(trim((string) ($payload['source_type'] ?? 'auto')));
        if ($sourceType === 'auto') {
            $sourceType = match (strtolower($collectionKey)) {
                'cartelera', 'events', 'eventos' => 'event_items',
                'museo', 'catalogo', 'catalog', 'fichas', 'collection_items' => 'catalog_items',
                default => 'cms_collection',
            };
        }

        $limitDefault = $blockKey === 'collection_timeline' ? 100 : 3;
        $limit = max(1, min(100, (int) ($payload['items_limit'] ?? $limitDefault)));
        $direction = strtolower((string) ($payload['order_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy = trim((string) ($payload['order_by'] ?? ''));
        if ($orderBy === '') {
            $orderBy = $blockKey === 'collection_timeline' ? 'published_at' : 'published_at';
        }
        $categoryId = max(0, (int) ($payload['category_id'] ?? 0));

        if ($sourceType === 'event_items') {
            $query = [
                'page' => 1,
                'per_page' => $limit,
                'filter' => ['status' => 'published'],
            ];
            $sort = match ($orderBy) {
                'entry.title', 'title' => 'title',
                'entry.event_type', 'event_type' => 'event_type',
                'entry.slug', 'slug' => 'slug',
                default => '',
            };
            if ($sort !== '') {
                $query['sort'] = ($direction === 'desc' ? '-' : '') . $sort;
            }

            return [
                'client' => 'event',
                'path' => 'public/events',
                'query' => $query,
                'scope' => 'events',
            ];
        }

        if ($sourceType === 'catalog_items') {
            $sort = match ($orderBy) {
                'entry.title', 'title', 'name' => 'name',
                'entry.slug', 'slug' => 'slug',
                'entry.origin', 'origin' => 'origin',
                'entry.period', 'period' => 'period',
                default => 'name',
            };

            return [
                'client' => 'catalog',
                'path' => 'public/catalog/collection-items',
                'query' => [
                    'page' => 1,
                    'per_page' => $limit,
                    'sort' => ($direction === 'desc' ? '-' : '') . $sort,
                    'filter' => ['is_active' => '1'] + ($categoryId > 0 ? ['category_id' => $categoryId] : []),
                    'fields' => 'id,uuid,name,slug,category_id,cover_file_id,cover_url,localized,summary',
                ],
                'scope' => 'collection_items',
            ];
        }

        if ($collectionKey === '') {
            return null;
        }

        $query = [
            'per_page' => $limit,
            'order_by' => $orderBy,
            'order_direction' => $direction,
            'include' => 'listing_content',
        ];
        if ($categoryId > 0) {
            $query['category_id'] = $categoryId;
        }

        return [
            'client' => 'cms',
            'path' => 'public/' . rawurlencode($locale) . '/entries/' . rawurlencode($collectionKey),
            'query' => $query,
            'scope' => 'entries',
        ];
    }
}
