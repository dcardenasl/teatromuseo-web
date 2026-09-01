<?php

declare(strict_types=1);

namespace App\Services;

/** Single public sitemap read, backed by the BFF's bounded CMS projection. */
final class SiteSitemapService extends BaseSiteService
{
    private const CACHE_TTL = 3600;

    /** @return array<string, mixed>|null */
    public function get(string $locale): ?array
    {
        return $this->fetchData(
            "public-read/{$locale}/sitemap",
            [],
            self::CACHE_TTL,
            'sitemap',
        );
    }
}
