<?php

declare(strict_types=1);

namespace App\Services;

class SiteTagService extends BaseSiteService
{
    private const CACHE_TTL = 600; // 10 minutes

    /**
     * List active tags for a collection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(string $lang, string $collectionKey): array
    {
        $data = $this->fetchData(
            "public/{$lang}/tags/{$collectionKey}",
            [],
            self::CACHE_TTL,
            'taxonomies'
        );

        if ($data === null) {
            return [];
        }

        if (is_array($data['data'] ?? null)) {
            return $data['data'];
        }

        return $data;
    }
}
