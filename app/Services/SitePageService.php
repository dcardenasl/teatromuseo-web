<?php

declare(strict_types=1);

namespace App\Services;

class SitePageService extends BaseSiteService
{
    /**
     * List all published pages for a language (for sitemap generation).
     *
     * @param array<string, mixed> $query
     * @return array<array<string, mixed>>
     */
    public function listAll(string $lang, array $query = []): array
    {
        return $this->fetchData("public-read/{$lang}/pages", $query, 600, 'pages') ?? [];
    }
}
