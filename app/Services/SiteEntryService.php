<?php

declare(strict_types=1);

namespace App\Services;

class SiteEntryService extends BaseSiteService
{
    private const CACHE_TTL_LIST = 180;

    /**
     * List entries in a collection with optional pagination and filtering.
     *
     * @param array<string, mixed> $query Query parameters: page, limit, category, tag, etc.
     *
     * @return array<string, mixed> {data: entries[], meta: {pagination: ...}}
     */
    public function list(string $lang, string $collectionKey, array $query = []): array
    {
        $response = $this->apiClient->get(
            "public-read/{$lang}/entries/{$collectionKey}",
            $query,
            self::CACHE_TTL_LIST,
            'entries'
        );

        if (! $response['ok']) {
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
}
