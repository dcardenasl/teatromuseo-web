<?php

declare(strict_types=1);

namespace App\Services;

class SiteCollectionService extends BaseSiteService
{
    private const CACHE_TTL = 3600; // 1 hour — collection list changes rarely; edits invalidate via CacheInvalidator regardless

    /**
     * Get all active collections for a language.
     *
     * @return array<array<string, mixed>>
     */
    public function getAll(string $lang): array
    {
        return $this->fetchData("public/{$lang}/collections", [], self::CACHE_TTL, 'collections') ?? [];
    }
}
