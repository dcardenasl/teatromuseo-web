<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PageController extends BasePublicWebController
{
    /**
     * Enforce locale prefix in the URL.
     * Returns a redirect response if redirection is needed, or null otherwise.
     */
    protected function enforceLocale(): ?ResponseInterface
    {
        $request = service('request');
        $uri = $request->getUri();
        $firstSegment = strtolower(trim((string) $uri->getSegment(1)));
        $supportedLocales = config('App')->supportedLocales;

        // The URL is authoritative for public content. Re-apply it at the
        // controller boundary so every downstream service (API client,
        // menus, pages, blocks and translations) sees the same locale even
        // when the framework negotiated a different browser language earlier.
        if (in_array($firstSegment, $supportedLocales, true)) {
            $request->setLocale($firstSegment);
        }

        if (!in_array($firstSegment, $supportedLocales, true)) {
            $locale = $request->getLocale();
            // Use getSegments() — CI4 already strips index.php from segments
            $segments = $uri->getSegments();
            $path = implode('/', $segments);
            $query = $uri->getQuery();
            $target = '/' . $locale . ($path !== '' ? '/' . $path : '') . ($query !== '' ? '?' . $query : '');
            return redirect()->to($target)->setStatusCode(302);
        }

        return null;
    }

    /**
     * Reads the preview query params off the incoming request and forwards
     * them opaquely — this app never validates the signature itself, only
     * Domain does (PreviewToken::verify). Passing an invalid or missing
     * signature through just means Domain falls back to published-only rules.
     *
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    private function previewParams(): array
    {
        $request = service('request');
        $preview = $request->getGet('preview') === '1';
        $previewExpires = $request->getGet('preview_expires');
        $previewSig = $request->getGet('preview_sig');

        return [
            $preview,
            is_string($previewExpires) ? $previewExpires : null,
            is_string($previewSig) ? $previewSig : null,
        ];
    }

    /**
     * Render the homepage.
     */
    public function home(): ResponseInterface
    {
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        [$preview, $previewExpires, $previewSig] = $this->previewParams();

        // For now, try to fetch a page by slug 'home'
        $pageService = Services::sitePageService();
        $page = $pageService->getBySlug($lang, 'home', $preview, $previewExpires, $previewSig);

        if (!$page) {
            return $this->notFound('Página de inicio no encontrada');
        }

        return $this->renderPage($page, $lang);
    }

    /**
     * Dynamic page resolver - implements the 5-step resolution algorithm.
     */
    public function resolve(string ...$segments): ResponseInterface
    {
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        $path = trim(implode('/', $segments), '/');
        [$preview, $previewExpires, $previewSig] = $this->previewParams();

        if (empty($path)) {
            return $this->home();
        }

        // Step 1: Try collection prefix match first.
        $collectionService = Services::siteCollectionService();
        $entryService = Services::siteEntryService();
        $pageService = Services::sitePageService();
        $collections = $collectionService->getAll($lang);

        foreach ($collections as $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $pathInfo = collection_url_path_info($collection, $path);
            if ($pathInfo === null) {
                continue;
            }

            $remainder = $pathInfo['remainder'];

            if ($remainder === '') {
                $page = $pageService->getBySlug($lang, $path, $preview, $previewExpires, $previewSig);
                if ($page && (string) ($page['page_type'] ?? '') === 'collection_index' && (int) ($page['collection_id'] ?? 0) === (int) ($collection['id'] ?? 0)) {
                    return $this->renderPage($page, $lang);
                }

                // The path matched this collection's prefix (its own index page,
                // or the collection_key fallback for collections without one) but
                // no matching collection_index page exists. Don't 404 here: a
                // collection lacking a dedicated index page can share its prefix
                // with an unrelated CMS page slug — let Steps 2-5 resolve it
                // normally instead of shadowing that page.
                break;
            }

            $entry = $entryService->getBySlug($lang, $collection['collection_key'], $remainder, $preview, $previewExpires, $previewSig);

            if ($entry) {
                return $this->renderEntry($entry, $collection, $lang);
            }
        }

        // Step 2: Support CMS pages that host collection listings directly
        // under their own slug, e.g. /es/festivales/{slug}. If the first
        // segment resolves to a page and that page contains a collection
        // listing block, treat the remainder as an entry slug.
        $pathSegments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        if (count($pathSegments) > 1) {
            $pageSlug = array_shift($pathSegments);
            $entrySlug = implode('/', $pathSegments);

            if ($pageSlug !== '' && $entrySlug !== '') {
                $page = $pageService->getBySlug($lang, $pageSlug, $preview, $previewExpires, $previewSig);
                if ($page) {
                    $collectionId = $this->resolveCollectionIdFromBlocks($page['blocks'] ?? []);
                    if ($collectionId > 0) {
                        $collection = $this->resolveCollectionById($collectionId, $lang);
                        if ($collection !== null) {
                            $entry = $entryService->getBySlug(
                                $lang,
                                (string) ($collection['collection_key'] ?? ''),
                                $entrySlug,
                                $preview,
                                $previewExpires,
                                $previewSig
                            );

                            if ($entry) {
                                return $this->renderEntry($entry, $collection, $lang);
                            }
                        }
                    }
                }
            }
        }

        // Step 3: Try CMS page by slug only when the path is not a collection route.
        $page = $pageService->getBySlug($lang, $path, $preview, $previewExpires, $previewSig);

        if ($page) {
            return $this->renderPage($page, $lang);
        }

        // Step 4: Try redirect
        $redirectService = Services::siteRedirectService();
        $redirect = $redirectService->resolve($path);

        if ($redirect) {
            $statusCode = match ($redirect['redirect_type'] ?? 301) {
                'temporary' => 302,
                'permanent' => 301,
                default => 301,
            };

            return redirect()->to((string) $redirect['new_url'])->setStatusCode($statusCode);
        }

        // Step 5: 404
        return $this->notFound("No se encontró la página: {$path}");
    }

    /**
     * Render a CMS page.
     *
     * @param array<string, mixed> $page
     */
    private function renderPage(array $page, string $lang): ResponseInterface
    {
        $blockRenderer = Services::blockRenderer();

        // Get the translation for the current language
        $translation = $this->getPageTranslation($page, $lang);
        $blocks = $page['blocks'] ?? [];
        $hasHeroHeading = false;
        foreach ($blocks as $block) {
            $blockKey = $block['block_key'] ?? '';
            if (in_array($blockKey, ['hero_slider', 'hero_banner', 'page_header'], true)) {
                $hasHeroHeading = true;
                break;
            }
        }

        $localizedUrls = [];
        foreach (($page['localized_slugs'] ?? []) as $loc => $slug) {
            if ($slug !== null) {
                $slugPath = trim($slug, '/');
                if ($slugPath === 'home' || $slugPath === '') {
                    $localizedUrls[$loc] = site_url('/' . $loc);
                } else {
                    $localizedUrls[$loc] = site_url('/' . $loc . '/' . ltrim($slugPath, '/'));
                }
            }
        }

        $data = [
            'title'              => $translation['title'] ?? '',
            'excerpt'            => $translation['excerpt'] ?? '',
            'showPageHeading'    => ! $hasHeroHeading,
            'pageTitle'          => (isset($translation['meta_title']) && trim((string) $translation['meta_title']) !== '') ? $translation['meta_title'] : ($translation['title'] ?? ''),
            'metaDescription'    => (isset($translation['meta_description']) && trim((string) $translation['meta_description']) !== '') ? $translation['meta_description'] : ($translation['excerpt'] ?? ''),
            'canonicalUrl'       => ($translation['canonical_url'] ?? '') !== ''
                ? $translation['canonical_url']
                : site_url('/' . $lang . '/' . ltrim((string) ($translation['slug'] ?? ''), '/')),
            'ogImage'            => is_array($translation['og_image'] ?? null)
                ? (string) ($translation['og_image']['url'] ?? '')
                : '',
            'metaRobots'         => (isset($translation['robots']) && trim((string) $translation['robots']) !== '') ? $translation['robots'] : 'index, follow',
            'schemaData'         => !empty($translation['schema_data']) ? json_decode($translation['schema_data'], true) : null,
            'renderedBlocks'     => $blockRenderer->render($blocks, $lang),
            'localized_urls'     => $localizedUrls,
        ];

        return $this->render('page', $data);
    }

    /**
     * Render a collection entry (single item).
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $collection
     */
    private function renderEntry(array $entry, array $collection, string $lang): ResponseInterface
    {
        $blockRenderer = Services::blockRenderer();

        // Get the translation for the current language
        $translation = $this->getEntryTranslation($entry, $lang);
        $resolvedSlug = trim((string) ($translation['slug'] ?? ''));
        if ($resolvedSlug === '') {
            $localizedSlugs = is_array($entry['localized_slugs'] ?? null) ? $entry['localized_slugs'] : [];
            $resolvedSlug = (string) ($localizedSlugs[$lang] ?? '');
            if ($resolvedSlug === '') {
                foreach ($localizedSlugs as $candidateSlug) {
                    $candidateSlug = trim((string) $candidateSlug);
                    if ($candidateSlug !== '') {
                        $resolvedSlug = $candidateSlug;
                        break;
                    }
                }
            }
        }

        $collectionUrlPath = collection_url_path($collection);
        if ($collectionUrlPath === '') {
            $collectionUrlPath = $this->currentCollectionPathFromRequest();
        }
        $canonicalUrl = ($translation['canonical_url'] ?? '') !== ''
            ? $translation['canonical_url']
            : site_url('/' . $lang . $collectionUrlPath . '/' . ltrim($resolvedSlug, '/'));

        $allowedOgTypes = ['article', 'website'];
        $ogType = in_array($translation['og_type'] ?? '', $allowedOgTypes, true) ? $translation['og_type'] : 'article';

        // The API serializes CodeIgniter Time fields (e.g. updated_at) as
        // {date, timezone_type, timezone} rather than a plain string.
        $updatedAtRaw = $entry['updated_at'] ?? null;
        $articleModifiedTime = is_array($updatedAtRaw) ? ($updatedAtRaw['date'] ?? null) : $updatedAtRaw;

        $relatedEntries = [];
        try {
            $relatedEntries = Services::siteEntryService()->related(
                $lang,
                $collection['collection_key'],
                ['slug' => $resolvedSlug, 'categories' => $entry['categories'] ?? []],
                3
            );
        } catch (\Throwable) {
            $relatedEntries = [];
        }

        // Entries whose own blocks already render a heading/hero image must not
        // duplicate the article template's hardcoded title/featured image.
        $hasHeroHeading = false;
        $hasHeroImage = false;
        foreach (($entry['blocks'] ?? []) as $block) {
            $blockKey = $block['block_key'] ?? '';
            if (in_array($blockKey, ['hero_slider', 'hero_banner', 'page_header'], true)) {
                $hasHeroHeading = true;
            }
            if (in_array($blockKey, ['hero_slider', 'hero_banner'], true)) {
                $hasHeroImage = true;
            }
        }

        $featuredImage = is_array($entry['featured_image'] ?? null) ? $entry['featured_image'] : [];
        $featuredImageUrl = is_string($featuredImage['url'] ?? null) ? trim((string) $featuredImage['url']) : '';
        $ogImage = is_array($translation['og_image'] ?? null) ? $translation['og_image'] : [];
        $ogImageUrl = is_string($ogImage['url'] ?? null) ? trim((string) $ogImage['url']) : '';
        if ($ogImageUrl === '') {
            $ogImageUrl = $featuredImageUrl;
        }

        if ($featuredImageUrl !== '' && !$hasHeroImage) {
            $srcsetString = '';
            $sizesString = '';
            $variants = $featuredImage['variants'] ?? null;
            if (is_array($variants) && !empty($variants)) {
                $srcsetItems = [];
                $widths = [];
                foreach ($variants as $v) {
                    if (isset($v['url'], $v['width'])) {
                        $w = (int) $v['width'];
                        $srcsetItems[] = esc($v['url']) . ' ' . $w . 'w';
                        $widths[] = $w;
                    }
                }
                if (!empty($srcsetItems) && !empty($widths)) {
                    $srcsetString = implode(', ', $srcsetItems);
                    sort($widths);
                    $maxWidth = max($widths);
                    $sizesString = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, ' . $maxWidth . 'px';
                }
            }
            $blockRenderer->addPreload($featuredImageUrl, $srcsetString, $sizesString);
        }

        $data = [
            'title'               => $translation['title'] ?? '',
            'excerpt'             => $translation['excerpt'] ?? '',
            'published_at'        => $entry['published_at'] ?? '',
            'featured_image'      => $featuredImage,
            'collection'          => $collection,
            'author_id'           => $entry['author_id'] ?? null,
            'categories'          => $entry['categories'] ?? [],
            'tags'                => $entry['tags'] ?? [],
            'collectionName'      => collection_display_title($collection),
            'collectionUrlPath'   => $collectionUrlPath,
            'relatedEntries'      => $relatedEntries,
            'showEntryHeading'    => ! $hasHeroHeading,
            'showFeaturedImage'   => ! $hasHeroImage,
            'lang'                => $lang,
            'pageTitle'           => (isset($translation['meta_title']) && trim((string) $translation['meta_title']) !== '') ? $translation['meta_title'] : ($translation['title'] ?? ''),
            'metaDescription'     => (isset($translation['meta_description']) && trim((string) $translation['meta_description']) !== '') ? $translation['meta_description'] : ($translation['excerpt'] ?? ''),
            'canonicalUrl'        => $canonicalUrl,
            'ogImage'             => $ogImageUrl,
            'ogType'              => $ogType,
            'articlePublishedTime' => $entry['published_at'] ?? null,
            'articleModifiedTime'  => $articleModifiedTime,
            'metaRobots'          => (isset($translation['robots']) && trim((string) $translation['robots']) !== '') ? $translation['robots'] : 'index, follow',
            'schemaData'          => !empty($translation['schema_data']) ? json_decode($translation['schema_data'], true) : null,
            'renderedBlocks'      => $blockRenderer->render($entry['blocks'] ?? [], $lang),
            'localized_urls'      => $this->resolveEntryLocalizedUrls($collection, $entry, $lang, $resolvedSlug),
        ];

        return $this->render('collection/show', $data);
    }

    /**
     * Extract translation data from a page based on language.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function getPageTranslation(array $page, string $lang): array
    {
        if (isset($page['title'])) {
            $translation = $page;
            if (! isset($translation['og_image']) && isset($page['translations']) && is_array($page['translations'])) {
                foreach ($page['translations'] as $trans) {
                    if (($trans['language_id'] ?? null) === $lang || ($trans['language_code'] ?? null) === $lang) {
                        $translation = array_merge($translation, $trans);
                        break;
                    }
                }
            }

            return $translation;
        }

        $translations = $page['translations'] ?? [];

        foreach ($translations as $trans) {
            if (($trans['language_id'] ?? null) === $lang || ($trans['language_code'] ?? null) === $lang) {
                return $trans;
            }
        }

        // Fallback to first translation
        return $translations[0] ?? [];
    }

    /**
     * Extract translation data from an entry based on language.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function getEntryTranslation(array $entry, string $lang): array
    {
        if (isset($entry['title'])) {
            $translation = $entry;
            if ((! isset($translation['og_image']) || ! isset($translation['featured_image'])) && isset($entry['translations']) && is_array($entry['translations'])) {
                foreach ($entry['translations'] as $trans) {
                    if (($trans['language_id'] ?? null) === $lang || ($trans['language_code'] ?? null) === $lang) {
                        $translation = array_merge($translation, $trans);
                        break;
                    }
                }
            }

            return $translation;
        }

        $translations = $entry['translations'] ?? [];

        foreach ($translations as $trans) {
            if (($trans['language_id'] ?? null) === $lang || ($trans['language_code'] ?? null) === $lang) {
                return $trans;
            }
        }

        // Fallback to first translation
        return $translations[0] ?? [];
    }

    /**
     * @param array<array<string, mixed>>|mixed $blocks
     */
    private function resolveCollectionIdFromBlocks(mixed $blocks): int
    {
        if (! is_array($blocks)) {
            return 0;
        }

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blockKey = (string) ($block['block_key'] ?? '');
            if (in_array($blockKey, ['collection_listing', 'collection_grid'], true)) {
                $collectionId = (int) (($block['block_config'] ?? [])['collection_id'] ?? 0);
                if ($collectionId > 0) {
                    return $collectionId;
                }
            }

            $childCollectionId = $this->resolveCollectionIdFromBlocks($block['children'] ?? []);
            if ($childCollectionId > 0) {
                return $childCollectionId;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCollectionById(int $collectionId, string $lang): ?array
    {
        $collectionService = Services::siteCollectionService();

        foreach ($collectionService->getAll($lang) as $collection) {
            if (is_array($collection) && (int) ($collection['id'] ?? 0) === $collectionId) {
                return $collection;
            }
        }

        return null;
    }

    private function currentCollectionPathFromRequest(): string
    {
        $request = service('request');
        $path = trim((string) $request->getUri()->getPath(), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $supportedLocales = config('App')->supportedLocales;
        if ($segments !== [] && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        if (count($segments) > 1) {
            array_pop($segments);
        }

        $fallbackPath = trim(implode('/', $segments), '/');

        return $fallbackPath !== '' ? '/' . $fallbackPath : '';
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private function resolveEntryLocalizedUrls(array $collection, array $entry, string $currentLang, string $resolvedSlug): array
    {
        $localizedUrls = localized_entry_urls($collection, $entry);
        if ($localizedUrls !== []) {
            return $localizedUrls;
        }

        $collectionSlugs = is_array($collection['localized_slugs'] ?? null) ? $collection['localized_slugs'] : [];
        $entrySlugs = is_array($entry['localized_slugs'] ?? null) ? $entry['localized_slugs'] : [];
        $fallbackCollectionPath = trim($this->currentCollectionPathFromRequest(), '/');
        $fallbackEntrySlug = trim($resolvedSlug, '/');

        foreach (config('App')->supportedLocales as $locale) {
            $collectionPath = trim((string) ($collectionSlugs[$locale] ?? ''), '/');
            if ($collectionPath === '' && $locale === $currentLang) {
                $collectionPath = $fallbackCollectionPath;
            }
            if ($collectionPath === '') {
                continue;
            }

            $entrySlug = trim((string) ($entrySlugs[$locale] ?? $fallbackEntrySlug), '/');
            if ($entrySlug === '') {
                continue;
            }

            $localizedUrls[$locale] = site_url('/' . $locale . '/' . $collectionPath . '/' . $entrySlug);
        }

        return $localizedUrls;
    }

}
