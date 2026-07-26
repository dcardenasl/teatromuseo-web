<?php

declare(strict_types=1);

namespace App\Services;

class SiteCollectionService extends BaseSiteService
{
    private const CACHE_TTL = 600; // 10 minutes

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
