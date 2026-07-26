<?php

declare(strict_types=1);

namespace App\Services;

class SiteCategoryService extends BaseSiteService
{
    private const CACHE_TTL = 600; // 10 minutes — categories change rarely

    /**
     * List active categories for a collection.
     *
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function list(string $lang, string $collectionKey): array
    {
        $data = $this->fetchData(
            "public/{$lang}/categories/{$collectionKey}",
            [],
            self::CACHE_TTL,
            'taxonomies'
        );

        if ($data === null) {
            return [];
        }

        // Some endpoints wrap the list in a nested `data` key.
        if (is_array($data['data'] ?? null)) {
            return $data['data'];
        }

        return $data;
    }
}
