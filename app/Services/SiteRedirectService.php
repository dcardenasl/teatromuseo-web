<?php

declare(strict_types=1);

namespace App\Services;

class SiteRedirectService extends BaseSiteService
{
    private const CACHE_TTL = 3600; // 1 hour (very stable)

    /**
     * Resolve a redirect by path.
     *
     * @return array<string, mixed>|null {new_url, redirect_type, ...}
     */
    public function resolve(string $path): ?array
    {
        return $this->fetchData("public/redirects/{$path}", [], self::CACHE_TTL, 'redirects');
    }
}
