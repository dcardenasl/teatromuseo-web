<?php

declare(strict_types=1);

namespace App\Services;

class SiteCatalogService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300; // 5 minutes
    private const CACHE_TTL_LIST = 180;   // 3 minutes
    private const LIST_FIELDS = 'id,name,category_id,inventory_code,status,summary,cover_image,slug,localized,category,created_at,updated_at';
    private const DETAIL_FIELDS = 'id,name,category_id,inventory_code,status,summary,curiosidad,contenido,origin,period,creator,ubicacion,materials,cover_file_id,cover_image,gallery_file_ids,gallery_images,collection_number,collection_group,physical_description,dimensions,ingress_type,donated_by,tags,links,company_history,localized,translations,slug,slugs,techniques,created_at,updated_at';

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
            "public-read/{$lang}/collection-items",
            $this->publicReadQuery($query),
            self::CACHE_TTL_LIST,
            'collection_items'
        );

        if (!$response['ok']) {
            return ['data' => [], 'meta' => ['pagination' => []]];
        }

        return [
            'data' => is_array($response['data']) ? $response['data'] : [],
            'meta' => [
                'pagination' => $this->normalizePagination(
                    is_array($response['meta'] ?? null) ? $response['meta'] : [],
                    isset($query['page']) ? (int) $query['page'] : 1,
                    isset($query['per_page']) ? (int) $query['per_page'] : 20
                ),
            ],
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
            "public-read/{$lang}/collection-items/" . rawurlencode($idOrCode),
            ['fields' => self::DETAIL_FIELDS],
            self::CACHE_TTL_DETAIL,
            'collection_items'
        );
    }

    /**
     * Translate the legacy nested-filter shape into the canonical PublicRead
     * query. Published and active state are enforced by the domain reader.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function publicReadQuery(array $query): array
    {
        $filter = is_array($query['filter'] ?? null) ? $query['filter'] : [];
        $sort = ltrim(trim((string) ($query['sort'] ?? 'name')), '-');
        $sort = in_array($sort, ['name', 'created_at', 'id'], true) ? $sort : 'name';

        $result = [
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($query['per_page'] ?? 20))),
            'sort' => $sort,
            'fields' => self::LIST_FIELDS,
        ];

        foreach (['search', 'technique', 'technique_id'] as $key) {
            $value = trim((string) ($query[$key] ?? $filter[$key] ?? ''));
            if ($value !== '') {
                $result[$key] = $value;
            }
        }

        $categoryId = (int) ($filter['category_id'] ?? $query['category_id'] ?? 0);
        if ($categoryId > 0) {
            $result['category_id'] = $categoryId;
        }

        $category = trim((string) ($query['category'] ?? $filter['category'] ?? ''));
        if ($category !== '') {
            $result['category'] = $category;
        }

        return $result;
    }
}
