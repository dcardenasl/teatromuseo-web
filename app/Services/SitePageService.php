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

        return $this->fetchData("public-read/{$lang}/pages/{$slug}", $query, $ttl, 'pages');
    }

    /**
     * Resolve the homepage even when its public slug is localized.
     *
     * The CMS owns the translated slug, so the homepage is not guaranteed to
     * be reachable through the legacy `home`/`inicio` aliases. The public-read
     * page index exposes the stable page_type together with the current slug;
     * use that slug to fetch the complete page payload.
     *
     * @return array<string, mixed>|null
     */
    public function getHomepage(
        string $lang,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSig = null
    ): ?array {
        foreach (['home', 'inicio'] as $slug) {
            $page = $this->getBySlug($lang, $slug, $preview, $previewExpires, $previewSig);
            if ($page !== null) {
                return $page;
            }
        }

        foreach ($this->listAll($lang) as $candidate) {
            if (($candidate['page_type'] ?? null) !== 'home') {
                continue;
            }

            $slug = trim((string) ($candidate['slug'] ?? ''));
            if ($slug === '' || in_array(strtolower($slug), ['home', 'inicio'], true)) {
                continue;
            }

            $page = $this->getBySlug($lang, $slug, $preview, $previewExpires, $previewSig);
            if ($page !== null) {
                return $page;
            }
        }

        // Keep the existing template endpoint as a compatibility fallback for
        // older domain deployments; current CMS versions reserve by-type for
        // catalog/event templates and resolve home through the page index.
        return $this->getByType($lang, 'home');
    }

    /**
     * List all published pages for a language (for sitemap generation).
     *
     * @return array<array<string, mixed>>
     */
    public function listAll(string $lang): array
    {
        return $this->fetchData("public-read/{$lang}/pages", [], self::CACHE_TTL_LIST, 'pages') ?? [];
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
