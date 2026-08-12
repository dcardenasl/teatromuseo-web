<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PublicPaths;

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
     * Resolve the homepage using the locale's public slug.
     *
     * `home` is the CMS page type and the English public slug. It is not the
     * Spanish homepage slug: Spanish publishes the page at `inicio`. Resolve
     * the canonical locale slug first, then use the public page index to find
     * a translated slug on older or less conventional deployments.
     *
     * @return array<string, mixed>|null
     */
    public function getHomepage(
        string $lang,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSig = null
    ): ?array {
        $page = $this->getBySlug(
            $lang,
            PublicPaths::homepageSegment($lang),
            $preview,
            $previewExpires,
            $previewSig,
        );
        if ($page !== null) {
            return $page;
        }

        foreach ($this->listAll($lang) as $candidate) {
            if (($candidate['page_type'] ?? null) !== 'home') {
                continue;
            }

            $slug = trim((string) ($candidate['slug'] ?? ''));
            if ($slug === '') {
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
     * @param array<string, mixed> $query
     * @return array<array<string, mixed>>
     */
    public function listAll(string $lang, array $query = []): array
    {
        return $this->fetchData("public-read/{$lang}/pages", $query, self::CACHE_TTL_LIST, 'pages') ?? [];
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
