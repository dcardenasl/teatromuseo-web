<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\WebApiClient;
use App\Libraries\WebApiClientInterface;

/**
 * Plans and resolves every remote dependency of a page's dynamic blocks before
 * BlockRenderer starts. ViewModels consume the path-keyed envelopes produced by
 * this service and never perform a lazy HTTP fallback.
 *
 * @phpstan-type PrefetchRequest array{client: string, path: string, query: array<string, mixed>, cacheTtl: int, scope: string}
 */
final class BlockPrefetchService
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

    /** @var array<string, WebApiClientInterface> */
    private array $clients = [];

    private string $planningLocale = 'es';

    /**
     * @param array<string, WebApiClientInterface> $clients
     */
    public function __construct(array $clients)
    {
        foreach ($clients as $name => $client) {
            if ($client instanceof WebApiClientInterface) {
                $this->clients[$name] = $client;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array{block_prefetch: array<string, array<string, mixed>>, block_prefetch_complete: bool}
     */
    public function prefetchContext(array $blocks, string $locale = 'es'): array
    {
        return [
            'block_prefetch' => $this->prefetch($blocks, $locale),
            'block_prefetch_complete' => true,
        ];
    }

    /**
     * Every planned dynamic block receives an explicit envelope, including
     * upstream failures. The renderer can therefore distinguish an empty result
     * from an absent plan without issuing a second request.
     *
     * @param list<array<string, mixed>> $blocks
     * @return array<string, array<string, mixed>>
     */
    public function prefetch(array $blocks, string $locale = 'es'): array
    {
        $this->planningLocale = $locale;
        /** @var array<string, array<string, mixed>> $plans */
        $plans = [];
        /** @var list<PrefetchRequest> $requests */
        $requests = [];
        $requestIndexes = [];
        $this->collectPlans($blocks, '', $locale, $plans);

        foreach ($plans as $blockPath => &$plan) {
            $plan['result'] = $this->emptyResult();
            $this->planInitialRequests($plan, $locale, $requests, $requestIndexes);
        }
        unset($plan);

        $initialRequestCount = count($requests);
        $responses = $this->executeRequests($requests);
        $this->resolveDependencies($plans, $responses, $locale, $requests, $requestIndexes);

        if (count($requests) > $initialRequestCount) {
            $responses = array_replace(
                $responses,
                $this->executeRequests($requests, $initialRequestCount),
            );
        }

        foreach ($plans as &$plan) {
            $this->materializePlan($plan, $responses);
        }
        unset($plan);

        /** @var array<string, array<string, mixed>> $results */
        $results = [];
        foreach ($plans as $blockPath => $plan) {
            $results[(string) $blockPath] = $plan['result'];
        }

        return $results;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param array<int|string, array<string, mixed>> $plans
     * @param-out array<int|string, array<string, mixed>> $plans
     */
    private function collectPlans(array $blocks, string $parentPath, string $locale, array &$plans): void
    {
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
                $this->collectPlans($childBlocks, $path, $locale, $plans);
            }
        }
    }

    private function isDynamicBlock(string $blockKey): bool
    {
        return in_array($blockKey, self::LIST_BLOCKS, true)
            || str_starts_with($blockKey, self::DETAIL_PREFIXES[0])
            || str_starts_with($blockKey, self::DETAIL_PREFIXES[1]);
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
            'result' => $this->emptyResult(),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<PrefetchRequest> $requests
     * @param array<string, int> $requestIndexes
     */
    private function planInitialRequests(array &$plan, string $locale, array &$requests, array &$requestIndexes): void
    {
        if ($plan['kind'] === 'detail') {
            $reference = $this->detailReference($plan['payload'], (string) $plan['block_key']);
            if ($reference === null) {
                $plan['result'] = $this->failedResult(422, 'Dynamic detail block has no id or slug.');
                return;
            }

            $definition = str_starts_with((string) $plan['block_key'], 'event_item_')
                ? ['client' => 'event', 'endpoint' => 'public/events', 'scope' => 'events']
                : ['client' => 'catalog', 'endpoint' => 'public/catalog/collection-items', 'scope' => 'collection_items'];
            if (! isset($this->clients[$definition['client']])) {
                $plan['result'] = $this->failedResult(503, 'Dynamic detail client is unavailable.');
                return;
            }

            $query = ['fields' => implode(',', $this->detailFields($plan['source_type']))];
            $path = $definition['endpoint'];
            if ($reference['kind'] === 'id') {
                $query['filter'] = ['id' => ['in' => [$reference['value']]]];
                $query['per_page'] = 1;
            } else {
                $path .= '/' . rawurlencode($reference['value']);
            }

            $plan['main_index'] = $this->addRequest(
                $requests,
                $requestIndexes,
                $definition['client'],
                $path,
                $query,
                300,
                $definition['scope'],
            );
            return;
        }

        $sourceType = $plan['source_type'];
        if (! in_array($sourceType, ['cms_collection', 'catalog_items', 'event_items'], true)) {
            $plan['result'] = $this->failedResult(422, 'Dynamic block has an invalid source type.');
            return;
        }

        if ($sourceType === 'cms_collection') {
            $collectionId = max(0, (int) ($plan['payload']['collection_id'] ?? 0));
            $collectionKey = trim((string) ($plan['payload']['collection_key'] ?? ''));
            $plan['collection_id'] = $collectionId;
            $plan['collection_key'] = $collectionKey;
            if ($collectionId > 0) {
                $plan['collection_index'] = $this->addRequest(
                    $requests,
                    $requestIndexes,
                    'cms',
                    'public/' . rawurlencode($locale) . '/collections',
                    [],
                    3600,
                    'collections',
                );
            }
            if ($collectionKey === '') {
                return;
            }
        }

        if ($sourceType === 'catalog_items' && $this->catalogNeedsCategoryDependency($plan)) {
            $plan['dependency_indexes']['categories'] = $this->addRequest(
                $requests,
                $requestIndexes,
                'catalog',
                'public/catalog/categories',
                [],
                600,
                'categories',
            );
            return;
        }

        $this->addListRequests($plan, $locale, $requests, $requestIndexes);
    }

    /**
     * @param array<string, mixed> $plan
     * @param list<PrefetchRequest> $requests
     * @param array<string, int> $requestIndexes
     */
    private function addListRequests(array &$plan, string $locale, array &$requests, array &$requestIndexes): void
    {
        $sourceType = $plan['source_type'];
        $query = $this->listQuery($plan, $sourceType);
        $client = 'cms';
        $path = '';
        $scope = 'entries';

        if ($sourceType === 'event_items') {
            $client = 'event';
            $path = 'public/events';
            $scope = 'events';
        } elseif ($sourceType === 'catalog_items') {
            $client = 'catalog';
            $path = 'public/catalog/collection-items';
            $scope = 'collection_items';
        } else {
            $collectionKey = trim((string) ($plan['collection_key'] ?? ''));
            if ($collectionKey === '') {
                $plan['result'] = $this->failedResult(422, 'CMS collection key could not be resolved.');
                return;
            }
            $path = 'public/' . rawurlencode($locale) . '/entries/' . rawurlencode($collectionKey);
        }

        $plan['main_index'] = $this->addRequest(
            $requests,
            $requestIndexes,
            $client,
            $path,
            $query,
            180,
            $scope,
        );
        $plan['main_query'] = $query;

        $showCategories = $this->wantsFacet($plan, 'categories');
        $showTags = $this->wantsFacet($plan, 'tags');
        if ($plan['block_key'] !== 'collection_listing' || (! $showCategories && ! $showTags)) {
            return;
        }

        if ($showCategories && $sourceType === 'cms_collection') {
            $plan['facet_indexes']['categories'] = $this->addRequest(
                $requests,
                $requestIndexes,
                'cms',
                'public/' . rawurlencode($locale) . '/categories/' . rawurlencode((string) $plan['collection_key']),
                [],
                600,
                'taxonomies',
            );
        } elseif ($showCategories && $sourceType === 'catalog_items') {
            $plan['facet_indexes']['categories'] = $this->addRequest(
                $requests,
                $requestIndexes,
                'catalog',
                'public/catalog/categories',
                [],
                600,
                'categories',
            );
        }

        if ($showTags && $sourceType === 'cms_collection') {
            $plan['facet_indexes']['tags'] = $this->addRequest(
                $requests,
                $requestIndexes,
                'cms',
                'public/' . rawurlencode($locale) . '/tags/' . rawurlencode((string) $plan['collection_key']),
                [],
                600,
                'taxonomies',
            );
        } elseif ($showTags && $sourceType === 'event_items') {
            $plan['facet_indexes']['tags'] = $this->addRequest(
                $requests,
                $requestIndexes,
                'event',
                'public/events/types',
                [],
                600,
                'event_types',
            );
        }
    }

    /** @param array<string, mixed> $plan */
    private function catalogNeedsCategoryDependency(array $plan): bool
    {
        return $plan['kind'] === 'list'
            && $this->categoryValue($plan) !== '';
    }

    /**
     * @param array<int|string, array<string, mixed>> $plans
     * @param array<int, array<string, mixed>> $responses
     * @param list<PrefetchRequest> $requests
     * @param array<string, int> $requestIndexes
     */
    private function resolveDependencies(array &$plans, array $responses, string $locale, array &$requests, array &$requestIndexes): void
    {
        foreach ($plans as &$plan) {
            if ($plan['kind'] === 'detail' || $plan['main_index'] !== null) {
                continue;
            }

            $collectionKey = trim((string) ($plan['collection_key'] ?? ''));
            if ($plan['collection_index'] !== null) {
                $collectionResponse = $responses[$plan['collection_index']] ?? null;
                $collection = $this->findCollection($collectionResponse, (int) ($plan['collection_id'] ?? 0));
                if ($collection !== null) {
                    $plan['collection'] = $collection;
                    $plan['collection_key'] = $collectionKey = trim((string) ($collection['collection_key'] ?? ''));
                }
            }

            if ($plan['source_type'] === 'catalog_items') {
                $category = $this->categoryValue($plan);
                if ($category !== '') {
                    $categoryResponse = isset($plan['dependency_indexes']['categories'])
                        ? ($responses[$plan['dependency_indexes']['categories']] ?? null)
                        : null;
                    $categoryId = $this->findCategoryId($categoryResponse, $category);
                    $plan['category_id'] = $categoryId;
                }
            }

            if ($plan['source_type'] === 'cms_collection' && $collectionKey === '') {
                $plan['result'] = $this->failedResult(404, 'CMS collection was not found.');
                continue;
            }

            $this->addListRequests($plan, $locale, $requests, $requestIndexes);
        }
        unset($plan);
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<int, array<string, mixed>> $responses
     */
    private function materializePlan(array &$plan, array $responses): void
    {
        $result = $plan['result'];
        $main = $plan['main_index'] !== null ? ($responses[$plan['main_index']] ?? null) : null;
        if (is_array($main)) {
            $result['ok'] = (bool) ($main['ok'] ?? false);
            $result['status'] = (int) ($main['status'] ?? 0);
            $result['data'] = $this->items($main['data'] ?? null);
            $result['meta'] = is_array($main['meta'] ?? null) ? $main['meta'] : [];
            if ($plan['kind'] === 'list' && ! isset($result['meta']['pagination'])) {
                $result['meta']['pagination'] = $this->normalizePagination(
                    $result['meta'],
                    is_array($plan['main_query'] ?? null) ? $plan['main_query'] : [],
                    count($result['data']),
                );
            }
            $result['stale'] = (bool) ($result['meta']['stale'] ?? false);
            $result['messages'] = $this->messages($main['messages'] ?? []);
        }

        if ($plan['kind'] === 'detail' && $result['data'] !== []) {
            $result['data'] = [$this->firstItem($result['data'])];
        }

        if (isset($plan['collection']) && is_array($plan['collection'])) {
            $result['collection'] = $plan['collection'];
        }

        foreach ($plan['facet_indexes'] as $facet => $index) {
            $response = $responses[$index] ?? null;
            $result['facets'][$facet] = is_array($response)
                ? $this->items($response['data'] ?? null)
                : [];
        }

        $plan['result'] = $result;
    }

    /**
     * @param list<PrefetchRequest> $requests
     * @return array<int, array<string, mixed>>
     */
    private function executeRequests(array $requests, int $offset = 0): array
    {
        if ($requests === [] || $offset >= count($requests)) {
            return [];
        }

        $pending = array_slice($requests, $offset);
        $responses = [];
        $allNative = true;
        foreach ($pending as $request) {
            $client = $this->clients[(string) ($request['client'] ?? '')] ?? null;
            if (! $client instanceof WebApiClient) {
                $allNative = false;
                break;
            }
        }

        if ($allNative) {
            /** @var list<array{client: WebApiClient, path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> $nativeRequests */
            $nativeRequests = [];
            foreach ($pending as $request) {
                $client = $this->clients[(string) $request['client']] ?? null;
                if (! $client instanceof WebApiClient) {
                    continue;
                }
                $nativeRequests[] = [
                    'client' => $client,
                    'path' => (string) $request['path'],
                    'query' => is_array($request['query']) ? $request['query'] : [],
                    'cacheTtl' => (int) $request['cacheTtl'],
                    'scope' => (string) $request['scope'],
                ];
            }
            foreach (WebApiClient::multiGetAcross($nativeRequests) as $index => $response) {
                $responses[$offset + $index] = $response;
            }
            return $responses;
        }

        $grouped = [];
        foreach ($pending as $index => $request) {
            $grouped[(string) $request['client']][] = [$index, $request];
        }
        foreach ($grouped as $clientKey => $group) {
            $client = $this->clients[$clientKey] ?? null;
            if (! $client instanceof WebApiClientInterface) {
                foreach ($group as [$index]) {
                    $responses[$offset + $index] = $this->failedResult(503, 'Prefetch client is unavailable.');
                }
                continue;
            }
            /** @var list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}> $batch */
            $batch = [];
            foreach ($group as $entry) {
                $request = $entry[1];
                $batch[] = [
                    'path' => (string) $request['path'],
                    'query' => is_array($request['query']) ? $request['query'] : [],
                    'cacheTtl' => (int) $request['cacheTtl'],
                    'scope' => (string) $request['scope'],
                ];
            }
            foreach ($client->multiGet($batch) as $index => $response) {
                $responses[$offset + $group[$index][0]] = is_array($response)
                    ? $response
                    : $this->failedResult(502, 'Invalid prefetch response.');
            }
        }

        return $responses;
    }

    /**
     * @param list<PrefetchRequest> $requests
     * @param array<string, int> $requestIndexes
     * @param-out list<PrefetchRequest> $requests
     * @param array<string, mixed> $query
     */
    private function addRequest(
        array &$requests,
        array &$requestIndexes,
        string $client,
        string $path,
        array $query,
        int $cacheTtl,
        string $scope,
    ): int {
        if ($this->isPreviewRequest()) {
            $query = array_merge($query, $this->previewQuery());
            $cacheTtl = 0;
        }

        $identity = $client . '|' . $this->planningLocale . '|' . $scope . '|' . $path . '|' . md5((string) json_encode($this->sortRecursive($query)));
        if (isset($requestIndexes[$identity])) {
            return $requestIndexes[$identity];
        }

        $index = count($requests);
        $requestIndexes[$identity] = $index;
        $requests[] = [
            'client' => $client,
            'path' => $path,
            'query' => $query,
            'cacheTtl' => $cacheTtl,
            'scope' => $scope,
        ];

        return $index;
    }

    /** @return array<string, mixed> */
    private function emptyResult(): array
    {
        return [
            'ok' => false,
            'status' => 0,
            'data' => [],
            'meta' => [],
            'facets' => ['categories' => [], 'tags' => []],
            'collection' => null,
            'stale' => false,
            'messages' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function failedResult(int $status, string $message): array
    {
        $result = $this->emptyResult();
        $result['status'] = $status;
        $result['messages'] = [$message];
        return $result;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function payload(array $block): array
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
    private function resolveSourceType(array $payload, string $blockKey = ''): string
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
                '[BlockPrefetchService] source_type=auto defaulted to CMS for collection_key "%s".',
                $collectionKey,
            ));
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{kind: 'id'|'slug', value: string}|null
     */
    private function detailReference(array $payload, string $blockKey): ?array
    {
        $idKeys = str_starts_with($blockKey, 'event_item_')
            ? ['event_id']
            : ['collection_item_id'];
        foreach ($idKeys as $key) {
            if (isset($payload[$key]) && (is_int($payload[$key]) || ctype_digit((string) $payload[$key])) && (int) $payload[$key] > 0) {
                return ['kind' => 'id', 'value' => (string) (int) $payload[$key]];
            }
        }
        $slugKeys = str_starts_with($blockKey, 'event_item_')
            ? ['event_slug']
            : ['collection_item_slug'];
        foreach ($slugKeys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return ['kind' => 'slug', 'value' => $value];
            }
        }
        return null;
    }

    /** @return list<string> */
    private function detailFields(string $sourceType): array
    {
        return $sourceType === 'event_items'
            ? ['id', 'uuid', 'title', 'slug', 'event_type', 'cover_file_id', 'cover_image', 'description', 'localized', 'translations', 'content', 'gallery_file_ids', 'gallery_images']
            : ['id', 'uuid', 'name', 'slug', 'inventory_code', 'cover_file_id', 'cover_url', 'cover_image', 'category_id', 'description', 'localized', 'translations', 'content', 'gallery_file_ids'];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function listQuery(array $plan, string $sourceType): array
    {
        $payload = $plan['payload'];
        $blockKey = (string) $plan['block_key'];
        $isListing = $blockKey === 'collection_listing';
        $limitDefault = $blockKey === 'collection_timeline' ? 100 : ($isListing ? 12 : 3);
        $configuredLimit = (int) ($payload['per_page'] ?? $payload['items_limit'] ?? $limitDefault);
        $requestLimit = $isListing ? $this->requestValue('limit', $this->requestValue('per_page')) : '';
        $limit = $configuredLimit;
        if ($requestLimit !== '' && ctype_digit($requestLimit) && (int) $requestLimit > 0) {
            $limit = (int) $requestLimit;
        }
        $limit = max(1, min(100, $limit));
        $page = $isListing ? max(1, (int) $this->requestValue('page', '1')) : 1;
        $projection = $payload['listing_projection'] ?? [];
        if (is_string($projection)) {
            $projection = json_decode($projection, true);
        }
        $projection = is_array($projection) ? $projection : [];
        $projectionOrder = is_array($projection['order'] ?? null) ? $projection['order'] : [];
        $publicOrdering = $this->truthy($projectionOrder['public'] ?? false);
        $configuredOrder = trim((string) ($payload['order_by'] ?? $projectionOrder['field'] ?? ''));
        $configuredDirection = strtolower((string) ($payload['order_direction'] ?? $projectionOrder['direction'] ?? 'desc'));
        $direction = $configuredDirection === 'asc' ? 'asc' : 'desc';
        if ($isListing && $publicOrdering) {
            $requestedDirection = strtolower($this->requestValue('order_direction'));
            if (in_array($requestedDirection, ['asc', 'desc'], true)) {
                $direction = $requestedDirection;
            }
        }
        $orderBy = $configuredOrder;
        if ($isListing && $publicOrdering && $this->requestValue('order_by') !== '') {
            $orderBy = $this->requestValue('order_by');
        }
        if ($orderBy === '') {
            $orderBy = $blockKey === 'collection_timeline' ? 'published_at' : ($sourceType === 'catalog_items' ? 'name' : 'published_at');
        }

        if ($sourceType === 'event_items') {
            $query = ['page' => $page, 'per_page' => $limit, 'filter' => ['status' => 'published']];
            $sort = match ($orderBy) {
                'entry.title', 'title' => 'title',
                'entry.event_type', 'event_type' => 'event_type',
                'entry.slug', 'slug' => 'slug',
                default => '',
            };
            if ($sort !== '') {
                $query['sort'] = ($direction === 'desc' ? '-' : '') . $sort;
            }
            if ($isListing && ($q = $this->requestValue('q')) !== '') {
                $query['search'] = $q;
            }
            $tag = $isListing ? $this->requestValue('tag') : '';
            if ($tag !== '') {
                $query['filter']['event_type'] = $tag;
            }
            return $query;
        }

        if ($sourceType === 'catalog_items') {
            $sort = match ($orderBy) {
                'entry.title', 'title', 'name' => 'name',
                'entry.slug', 'slug' => 'slug',
                'entry.origin', 'origin' => 'origin',
                'entry.period', 'period' => 'period',
                default => 'name',
            };
            $query = [
                'page' => $page,
                'per_page' => $limit,
                'sort' => ($direction === 'desc' ? '-' : '') . $sort,
                'filter' => ['is_active' => '1'],
                'fields' => $isListing ? 'id,uuid,name,slug,inventory_code,category_id,cover_file_id,cover_url,cover_image,localized,summary' : 'id,uuid,name,slug,category_id,cover_file_id,cover_url,localized,summary',
            ];
            $categoryId = max(0, (int) ($plan['category_id'] ?? $payload['category_id'] ?? 0));
            if ($categoryId > 0) {
                $query['filter']['category_id'] = $categoryId;
            }
            if ($isListing && ($q = $this->requestValue('q')) !== '') {
                $query['search'] = $q;
            }
            return $query;
        }

        $query = [
            'page' => $page,
            'per_page' => $limit,
            'order_by' => $this->cmsOrderField($orderBy),
            'order_direction' => $direction,
            'include' => 'listing_content',
        ];
        if ($isListing) {
            foreach (['category', 'tag', 'q', 'filter_by', 'filter_value', 'filter_operator'] as $key) {
                $value = $this->requestValue($key);
                if ($value !== '' && ($key !== 'filter_operator' || $value === 'contains')) {
                    $query[$key] = $value;
                }
            }
        } elseif (($categoryId = max(0, (int) ($payload['category_id'] ?? 0))) > 0) {
            $query['category_id'] = $categoryId;
        }
        $fields = array_merge(
            ['id', 'slug', 'title', 'excerpt', 'summary', 'published_at', 'featured_image', 'listing_content'],
            $this->projectionFields($projection),
        );
        $fields = array_values(array_unique($fields));
        if ($fields !== []) {
            $query['fields'] = implode(',', $fields);
        }
        return $query;
    }

    private function cmsOrderField(string $orderBy): string
    {
        return str_starts_with($orderBy, 'entry.')
            || str_starts_with($orderBy, 'block.')
            || str_starts_with($orderBy, 'taxonomy.')
            ? 'field:' . $orderBy
            : $orderBy;
    }

    /**
     * @param array<string, mixed> $projection
     * @return list<string>
     */
    private function projectionFields(array $projection): array
    {
        $fields = [];
        foreach (['title', 'subtitle', 'summary', 'date', 'image'] as $slot) {
            $source = is_array($projection['slots'] ?? null) ? trim((string) ($projection['slots'][$slot] ?? '')) : '';
            if ($source !== '') {
                $fields[] = $source;
            }
        }
        foreach (['extras', 'filters'] as $group) {
            foreach (is_array($projection[$group] ?? null) ? $projection[$group] : [] as $item) {
                if (is_array($item) && trim((string) ($item['source'] ?? '')) !== '') {
                    $fields[] = trim((string) $item['source']);
                }
            }
        }
        return array_values(array_unique($fields));
    }

    /**
     * Keep the block result contract compatible with the richer pagination
     * shape consumed by the listing templates, even when a domain response
     * only returns compact total/page/per_page metadata.
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizePagination(array $meta, array $query, int $dataCount): array
    {
        $total = (int) ($meta['total'] ?? $meta['total_items'] ?? $meta['count'] ?? $dataCount);
        $page = (int) ($meta['page'] ?? $meta['current_page'] ?? $query['page'] ?? 1);
        $perPage = (int) ($meta['per_page'] ?? $meta['perPage'] ?? $query['per_page'] ?? max(1, $dataCount));

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'total' => $total,
            'total_items' => $total,
            'page' => $page,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_next_page' => $page < $totalPages,
            'has_previous_page' => $page > 1,
        ];
    }

    private function requestValue(string $key, string $default = ''): string
    {
        try {
            $value = service('request')->getGet($key);
            return is_scalar($value) ? trim((string) $value) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function isPreviewRequest(): bool
    {
        return in_array(strtolower($this->requestValue('preview')), ['1', 'true', 'yes'], true);
    }

    /** @param array<string, mixed> $plan */
    private function categoryValue(array $plan): string
    {
        $payload = is_array($plan['payload'] ?? null) ? $plan['payload'] : [];
        $configured = trim((string) ($payload['category'] ?? ''));

        return $configured !== '' ? $configured : $this->requestValue('category');
    }

    /** @return array<string, string> */
    private function previewQuery(): array
    {
        if (! $this->isPreviewRequest()) {
            return [];
        }

        $query = ['preview' => '1'];
        foreach (['preview_expires', 'preview_sig'] as $key) {
            $value = $this->requestValue($key);
            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $plan */
    private function wantsFacet(array $plan, string $facet): bool
    {
        $key = $facet === 'categories' ? 'show_categories' : 'show_tags';
        $payload = is_array($plan['payload'] ?? null) ? $plan['payload'] : [];
        if (array_key_exists($key, $payload)) {
            return $this->truthy($payload[$key]);
        }

        return $facet === 'categories'
            ? in_array($plan['source_type'] ?? '', ['cms_collection', 'catalog_items'], true)
            : ($plan['source_type'] ?? '') === 'event_items';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return list<array<string, mixed>> */
    private function items(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        if (! array_is_list($data)) {
            return [$data];
        }
        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function firstItem(array $items): array
    {
        return is_array($items[0] ?? null) ? $items[0] : [];
    }

    /**
     * @param array<string, mixed>|null $response
     * @return array<string, mixed>|null
     */
    private function findCollection(?array $response, int $collectionId): ?array
    {
        if (! is_array($response) || ! ($response['ok'] ?? false)) {
            return null;
        }
        foreach ($this->items($response['data'] ?? null) as $collection) {
            if ((int) ($collection['id'] ?? 0) === $collectionId) {
                return $collection;
            }
        }
        return null;
    }

    /** @param array<string, mixed>|null $response */
    private function findCategoryId(?array $response, string $slug): int
    {
        if (! is_array($response) || ! ($response['ok'] ?? false)) {
            return 0;
        }
        foreach ($this->items($response['data'] ?? null) as $category) {
            if (trim((string) ($category['slug'] ?? ''), '/') === trim($slug, '/')) {
                return max(0, (int) ($category['id'] ?? 0));
            }
        }
        return 0;
    }

    /** @return list<string> */
    private function messages(mixed $messages): array
    {
        return is_array($messages)
            ? array_values(array_filter($messages, 'is_string'))
            : [];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        return $value;
    }
}
