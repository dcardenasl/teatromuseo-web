<?php

declare(strict_types=1);

namespace App\Services;

class SiteCatalogService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300; // 5 minutes
    private const CACHE_TTL_LIST = 180;   // 3 minutes

    /**
     * List categories of the catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(string $lang): array
    {
        $response = $this->apiClient->get(
            'public/catalog/categories',
            [],
            self::CACHE_TTL_LIST,
            'categories'
        );

        return is_array($response['data']) ? $response['data'] : [];
    }

    /**
     * List techniques of the catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTechniques(string $lang): array
    {
        $response = $this->apiClient->get(
            'public/catalog/techniques',
            [],
            self::CACHE_TTL_LIST,
            'techniques'
        );

        return is_array($response['data']) ? $response['data'] : [];
    }

    /**
     * Get a single technique by slug or ID.
     *
     * @return array<string, mixed>|null
     */
    public function getTechnique(string $lang, string $slug): ?array
    {
        return $this->fetchData(
            "public/catalog/techniques/{$slug}",
            [],
            self::CACHE_TTL_DETAIL,
            'techniques'
        );
    }

    /**
     * List collection items with optional query filters.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listItems(string $lang, array $query = []): array
    {
        $response = $this->apiClient->get(
            'public/catalog/collection-items',
            $query,
            self::CACHE_TTL_LIST,
            'collection_items'
        );

        if (!$response['ok']) {
            return ['data' => [], 'meta' => ['pagination' => []]];
        }

        return [
            'data' => is_array($response['data']) ? $response['data'] : [],
            'meta' => ['pagination' => $response['meta']],
        ];
    }

    /**
     * Get a single collection item by ID or inventory code.
     *
     * @return array<string, mixed>|null
     */
    public function getItem(string $lang, string $idOrCode): ?array
    {
        return $this->fetchData(
            "public/catalog/collection-items/{$idOrCode}",
            [],
            self::CACHE_TTL_DETAIL,
            'collection_items'
        );
    }
}
