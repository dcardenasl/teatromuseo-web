<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\SmartPrefetchInterface;
use App\Libraries\WebApiClientInterface;

class SmartPrefetchService implements SmartPrefetchInterface
{
    /**
     * Mapping of resource types to their owning client, endpoint and fields.
     * Paths are relative to WebApiClient::buildUrl(), which adds /api/v1/.
     *
     * @var array<string, array{client: string, endpoint: string, default_fields: list<string>}>
     */
    private const RESOURCE_ENDPOINTS = [
        'collection_items' => [
            'client' => 'catalog',
            'endpoint' => 'public/catalog/collection-items',
            'default_fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url'],
        ],
        'events' => [
            'client' => 'event',
            'endpoint' => 'public/events',
            'default_fields' => ['id', 'uuid', 'title', 'slug', 'event_type', 'cover_file_id', 'cover_image'],
        ],
        'categories' => [
            'client' => 'catalog',
            'endpoint' => 'public/catalog/categories',
            'default_fields' => ['id', 'name', 'slug'],
        ],
        'techniques' => [
            'client' => 'catalog',
            'endpoint' => 'public/catalog/techniques',
            'default_fields' => ['id', 'name', 'slug'],
        ],
    ];

    /** @var array<string, WebApiClientInterface> */
    private array $clients = [];

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

    public function prefetch(array $requirements, string $locale = 'es'): array
    {
        unset($locale);

        if (empty($requirements)) {
            return [];
        }

        $results = [];
        /** @var array<string, list<array{resource_type: string, ids: list<int|string>}>> $requestMap */
        $requestMap = [];
        /** @var array<string, list<array{path: string, query: array<string, mixed>, cacheTtl: int, scope: string}>> $requestsByClient */
        $requestsByClient = [];

        foreach ($requirements as $resourceType => $reqs) {
            if (!isset(self::RESOURCE_ENDPOINTS[$resourceType])) {
                continue;
            }

            $definition = self::RESOURCE_ENDPOINTS[$resourceType];
            $client = $this->clients[$definition['client']] ?? null;
            if (!$client instanceof WebApiClientInterface) {
                continue;
            }

            $clientKey = $definition['client'];
            $ids = array_values(array_filter(
                is_array($reqs['ids'] ?? null) ? $reqs['ids'] : [],
                static fn (mixed $id): bool => is_int($id) || (is_string($id) && trim($id) !== ''),
            ));
            $slugs = array_values(array_filter(
                is_array($reqs['slugs'] ?? null) ? $reqs['slugs'] : [],
                static fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '',
            ));
            $fields = array_values(array_filter(
                is_array($reqs['fields'] ?? null) ? $reqs['fields'] : $definition['default_fields'],
                static fn (mixed $field): bool => is_string($field) && trim($field) !== '',
            ));
            $query = $fields === [] ? [] : ['fields' => implode(',', $fields)];

            if ($ids !== []) {
                // Filter at the domain to avoid downloading an entire
                // catalogue just to discard unrelated items.
                $query['filter'] = ['id' => ['in' => $ids]];
                $query['per_page'] = min(100, max(1, count($ids)));
                $requestsByClient[$clientKey][] = [
                    'path' => $definition['endpoint'],
                    'query' => $query,
                    'cacheTtl' => 300,
                    'scope' => $resourceType,
                ];
                $requestMap[$clientKey][] = [
                    'resource_type' => $resourceType,
                    'ids' => $ids,
                ];
            }

            // Slugs cannot use the numeric id filter. Resolve them through
            // the public detail endpoint in the same domain-level batch.
            foreach ($slugs as $slug) {
                $requestsByClient[$clientKey][] = [
                    'path' => $definition['endpoint'] . '/' . rawurlencode((string) $slug),
                    'query' => $fields === [] ? [] : ['fields' => implode(',', $fields)],
                    'cacheTtl' => 300,
                    'scope' => $resourceType,
                ];
                $requestMap[$clientKey][] = [
                    'resource_type' => $resourceType,
                    'ids' => [],
                ];
            }
        }

        if ($requestsByClient === []) {
            return [];
        }

        // Each client owns a different base URL, so batch within each domain.
        // WebApiClient::multiGet() executes the requests in that group in parallel.
        foreach ($requestsByClient as $clientKey => $requests) {
            $client = $this->clients[$clientKey] ?? null;
            if (!$client instanceof WebApiClientInterface) {
                continue;
            }

            $responses = $client->multiGet($requests);
            foreach ($responses as $index => $response) {
                $request = $requestMap[$clientKey][$index] ?? null;
                if ($request === null || !isset($response['data']) || !is_array($response['data'])) {
                    continue;
                }

                $resourceType = $request['resource_type'];
                $requestedIds = array_fill_keys(
                    array_map(static fn (int|string $id): string => (string) $id, $request['ids']),
                    true,
                );
                $results[$resourceType] ??= [];
                $data = $response['data'];

                // Handle list, paginated, and single-detail responses.
                $items = isset($data['data']) && is_array($data['data'])
                    ? $data['data']
                    : (array_is_list($data) ? $data : [$data]);
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $itemId = $item['id'] ?? null;
                    if ($itemId !== null && ($requestedIds === [] || isset($requestedIds[(string) $itemId]))) {
                        $results[$resourceType][$itemId] = $item;
                    }
                }
            }
        }

        return $results;
    }

    public function prefetchBatch(string $resourceType, array $ids, array $fields = [], string $locale = 'es'): array
    {
        unset($locale);

        if (!isset(self::RESOURCE_ENDPOINTS[$resourceType]) || empty($ids)) {
            return [];
        }

        $definition = self::RESOURCE_ENDPOINTS[$resourceType];
        $client = $this->clients[$definition['client']] ?? null;
        if (!$client instanceof WebApiClientInterface) {
            return [];
        }

        $fieldsToUse = !empty($fields) ? $fields : $definition['default_fields'];
        $query = [
            'fields' => implode(',', $fieldsToUse),
            'filter' => ['id' => ['in' => array_values($ids)]],
            'per_page' => min(100, max(1, count($ids))),
        ];

        $response = $client->get($definition['endpoint'], $query, 300, $resourceType);

        $results = [];
        if (!isset($response['data']) || !is_array($response['data'])) {
            return $results;
        }

        $requestedIds = array_fill_keys(
            array_map(static fn (int|string $id): string => (string) $id, $ids),
            true,
        );
        $data = $response['data'];

        // Handle list, paginated, and single-detail responses.
        $items = isset($data['data']) && is_array($data['data'])
            ? $data['data']
            : (array_is_list($data) ? $data : [$data]);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = $item['id'] ?? null;
            if ($itemId !== null && isset($requestedIds[(string) $itemId])) {
                $results[$itemId] = $item;
            }
        }

        return $results;
    }
}
