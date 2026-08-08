<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\SmartPrefetchInterface;
use App\Libraries\WebApiClientInterface;

class SmartPrefetchService implements SmartPrefetchInterface
{
    /**
     * Mapping of resource types to their API endpoints and field defaults.
     *
     * @var array<string, array{endpoint: string, default_fields: array<string>}>
     */
    private const RESOURCE_ENDPOINTS = [
        'collection_items' => [
            'endpoint' => 'catalog/collection-items',
            'default_fields' => ['id', 'uuid', 'name', 'slug', 'cover_file_id', 'cover_url'],
        ],
        'events' => [
            'endpoint' => 'events/events',
            'default_fields' => ['id', 'uuid', 'title', 'slug', 'event_type', 'cover_file_id', 'cover_image'],
        ],
        'categories' => [
            'endpoint' => 'catalog/categories',
            'default_fields' => ['id', 'name', 'slug'],
        ],
        'techniques' => [
            'endpoint' => 'catalog/techniques',
            'default_fields' => ['id', 'name', 'slug'],
        ],
    ];

    public function __construct(
        private WebApiClientInterface $webApiClient
    ) {
    }

    public function prefetch(array $requirements, string $locale = 'es'): array
    {
        if (empty($requirements)) {
            return [];
        }

        $results = [];
        $batch = [];

        foreach ($requirements as $resourceType => $reqs) {
            if (!isset(self::RESOURCE_ENDPOINTS[$resourceType])) {
                continue;
            }

            $ids = $reqs['ids'] ?? [];
            $fields = $reqs['fields'] ?? self::RESOURCE_ENDPOINTS[$resourceType]['default_fields'];

            if (empty($ids)) {
                continue;
            }

            // Build URL with sparse fieldset parameter
            $endpoint = self::RESOURCE_ENDPOINTS[$resourceType]['endpoint'];
            $fieldsParam = !empty($fields) ? '?fields=' . implode(',', array_map('urlencode', $fields)) : '';
            $url = "/api/v1/public/{$endpoint}{$fieldsParam}";

            // Collect batch queries for parallel execution
            $batch[$resourceType] = [
                'url' => $url,
                'ids' => $ids,
                'fields' => $fields,
            ];
        }

        if (empty($batch)) {
            return [];
        }

        // Build multiGet requests for parallel execution
        $multiGetRequests = [];
        $requestIndexMap = [];  // Maps request index to resource type

        foreach ($batch as $resourceType => $req) {
            $index = count($multiGetRequests);
            $requestIndexMap[$index] = $resourceType;
            $multiGetRequests[] = ['path' => $req['url']];
        }

        // Fetch all resources in parallel
        $responses = $this->webApiClient->multiGet($multiGetRequests);

        // Process responses by resource type
        foreach ($responses as $index => $response) {
            if (!isset($requestIndexMap[$index])) {
                continue;
            }

            $resourceType = $requestIndexMap[$index];

            if (!isset($response['data'])) {
                continue;
            }

            $results[$resourceType] = [];
            $data = $response['data'];

            if (!is_array($data)) {
                continue;
            }

            // Handle both indexed arrays and paginated results
            $items = $data;
            if (isset($data['data']) && is_array($data['data'])) {
                $items = $data['data'];
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemId = $item['id'] ?? null;
                if ($itemId !== null) {
                    $results[$resourceType][$itemId] = $item;
                }
            }
        }

        return $results;
    }

    public function prefetchBatch(string $resourceType, array $ids, array $fields = [], string $locale = 'es'): array
    {
        if (!isset(self::RESOURCE_ENDPOINTS[$resourceType])) {
            return [];
        }

        if (empty($ids)) {
            return [];
        }

        $fieldsToUse = !empty($fields) ? $fields : self::RESOURCE_ENDPOINTS[$resourceType]['default_fields'];
        $endpoint = self::RESOURCE_ENDPOINTS[$resourceType]['endpoint'];
        $fieldsParam = '?fields=' . implode(',', array_map('urlencode', $fieldsToUse));
        $url = "/api/v1/public/{$endpoint}{$fieldsParam}";

        $response = $this->webApiClient->get($url);

        $results = [];
        if (!isset($response['data']) || !is_array($response['data'])) {
            return $results;
        }

        $data = $response['data'];

        // Handle both indexed arrays and paginated results
        $items = $data;
        if (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = $item['id'] ?? null;
            if ($itemId !== null && in_array($itemId, $ids, true)) {
                $results[$itemId] = $item;
            }
        }

        return $results;
    }
}
