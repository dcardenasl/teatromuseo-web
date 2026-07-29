<?php

declare(strict_types=1);

namespace App\Services;

class SitePageService extends BaseSiteService
{
    private const CACHE_TTL_DETAIL = 300; // 5 minutes for single page
    private const CACHE_TTL_LIST = 600;   // 10 minutes for list

    /**
     * Get a page by slug (with optional preview support).
     *
     * preview_expires/preview_sig are forwarded opaquely — this app never
     * validates them, only Domain does (PreviewToken::verify).
     *
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $lang, string $slug, bool $preview = false, ?string $previewExpires = null, ?string $previewSig = null): ?array
    {
        $query = [];
        if ($preview) {
            $query['preview'] = '1';
            if ($previewExpires !== null) {
                $query['preview_expires'] = $previewExpires;
            }
            if ($previewSig !== null) {
                $query['preview_sig'] = $previewSig;
            }
        }
        $ttl = $preview ? 0 : self::CACHE_TTL_DETAIL;

        return $this->fetchData("public/{$lang}/pages/{$slug}", $query, $ttl, 'pages');
    }

    /**
     * List all published pages for a language (for sitemap generation).
     *
     * @return array<array<string, mixed>>
     */
    public function listAll(string $lang): array
    {
        return $this->fetchData("public/{$lang}/pages", [], self::CACHE_TTL_LIST, 'pages') ?? [];
    }

    /**
     * Get a singleton template page by type.
     *
     * @return array<string, mixed>|null
     */
    public function getByType(string $lang, string $type): ?array
    {
        return $this->fetchData("public/{$lang}/pages/by-type/{$type}", [], self::CACHE_TTL_DETAIL, 'pages');
    }
}
