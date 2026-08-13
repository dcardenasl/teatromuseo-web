<?php

declare(strict_types=1);

namespace App\Controllers;

use App\PageDelivery\PageDeliveryRequest;
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
     * Render the homepage.
     */
    public function home(): ResponseInterface
    {
        $this->beginRouteResolution();
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();

        return $this->renderHomepage($lang, $preview, $previewExpires, $previewSig);
    }

    private function renderHomepage(
        string $lang,
        bool $preview,
        ?string $previewExpires,
        ?string $previewSig,
    ): ResponseInterface {

        $pageDeliveryEnabled = config('App')->pageDeliveryEnabled || $preview;
        if ($pageDeliveryEnabled) {
            $query = $this->request->getGet();
            $query = is_array($query) ? $query : [];
            $this->finishRouteResolution();

            return $this->renderDeliveredPage(
                Services::pageDelivery()->deliver(PageDeliveryRequest::home(
                    locale: $lang,
                    preview: $preview,
                    previewExpires: $previewExpires,
                    previewSignature: $previewSig,
                    query: $query,
                )),
                $lang,
            );
        }

        $pageService = Services::sitePageService();

        $page = $pageService->getHomepage($lang, $preview, $previewExpires, $previewSig);

        if (! $page) {
            return $this->notFound('Página de inicio no encontrada');
        }

        return $this->renderCmsPage($page, $lang);
    }

    /**
     * Dynamic page resolver - implements the 5-step resolution algorithm.
     */
    public function resolve(string ...$segments): ResponseInterface
    {
        $this->beginRouteResolution();
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        $path = trim(implode('/', $segments), '/');
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();

        if (empty($path)) {
            return $this->home();
        }

        // Older beta deployments leaked the internal `public/{locale}` base
        // path into cached homepage redirects. Keep those stale URLs
        // recoverable for visitors whose browser still has the redirect.
        if (strcasecmp($path, 'public/' . $lang) === 0) {
            return redirect()
                ->to(lang_url(\App\Support\PublicPaths::homepagePath($lang), $lang))
                ->setStatusCode(301);
        }

        // Keep legacy homepage aliases working while exposing the locale's
        // public homepage slug as the canonical URL.
        if (\App\Support\PublicPaths::isHomepageSlug($path, $lang)) {
            $canonicalPath = \App\Support\PublicPaths::homepagePath($lang);
            if (trim($path, '/') !== trim($canonicalPath, '/')) {
                return redirect()->to(lang_url($canonicalPath, $lang))->setStatusCode(301);
            }

            return $this->renderHomepage($lang, $preview, $previewExpires, $previewSig);
        }

        if ($delivery = $this->deliverConfiguredPageRoute($lang, $path, $preview, $previewExpires, $previewSig)) {
            return $delivery;
        }

        // Steps 1 & 2: Resolve redirects and fetch page in parallel (independent calls).
        // After fetching, check redirect result first. If no redirect, use page result and proceed to fallbacks.
        $pageService = Services::sitePageService();

        $parallelResults = Services::pageResolverService()->parallelResolveRedirectAndPage(
            $path,
            $lang,
            $preview,
            $previewExpires,
            $previewSig
        );
        $redirect = $parallelResults['redirect'];
        $page = $parallelResults['page'];

        if ($redirect) {
            $statusCode = match ($redirect['redirect_type'] ?? 301) {
                'temporary' => 302,
                'permanent' => 301,
                default => 301,
            };

            // Redirect destinations in the CMS are locale-less. Normalize
            // known canonical routes before adding the current locale so a
            // legacy `/pt/obras` request lands on `/pt/programacao`, not on
            // the Spanish fallback `/pt/cartelera`.
            $redirectPath = (string) ($redirect['new_url'] ?? '');
            $parsedRedirect = parse_url(trim($redirectPath));
            $isExternalRedirect = is_array($parsedRedirect)
                && (($parsedRedirect['scheme'] ?? '') !== '' || ($parsedRedirect['host'] ?? '') !== '');
            if (! $isExternalRedirect) {
                $localizedCanonicalPath = \App\Support\PublicPaths::canonicalPath($redirectPath, $lang);
                if ($localizedCanonicalPath !== null) {
                    $redirectPath = $localizedCanonicalPath;
                }
            }

            return redirect()->to(lang_url($redirectPath, $lang))->setStatusCode($statusCode);
        }

        if ($page && ! $this->isExactPageSlugMatch($page, $path, $lang)) {
            $page = null;
        }

        if (! $page) {
            $canonicalPath = \App\Support\PublicPaths::canonicalPath($path, $lang);
            if ($canonicalPath !== null && $canonicalPath !== '/' . $path) {
                $targetSlug = trim($canonicalPath, '/');
                $candidatePage = $pageService->getBySlug($lang, $targetSlug, $preview, $previewExpires, $previewSig);
                if ($candidatePage && $this->isExactPageSlugMatch($candidatePage, $targetSlug, $lang)) {
                    $page = $candidatePage;
                }
            }
        }

        if (! $page) {
            $aliasCandidates = match ($path) {
                'contacto' => ['contact'],
                'contact' => ['contacto'],
                'historia' => ['history', 'nossa-historia'],
                'history' => ['historia'],
                'cartelera' => ['events', 'eventos', 'programming'],
                'events' => ['cartelera'],
                default => [],
            };

            foreach ($aliasCandidates as $candidate) {
                $candidatePage = $pageService->getBySlug($lang, $candidate, $preview, $previewExpires, $previewSig);
                if ($candidatePage && $this->isExactPageSlugMatch($candidatePage, $candidate, $lang)) {
                    $page = $candidatePage;
                    break;
                }
            }
        }

        if ($page) {
            return $this->renderCmsPage($page, $lang);
        }



        // Step 3: Try collection entry match (e.g. /es/festivales/mi-evento).
        $collectionService = Services::siteCollectionService();
        $entryService = Services::siteEntryService();
        $collections = $collectionService->getAll($lang);
        $collectionCandidates = $this->collectionCandidatesForPath($collections, $path);

        // Only collections whose canonical prefix matches the request may
        // receive an entry lookup. Unknown paths never probe every collection.
        foreach ($collectionCandidates as $candidate) {
            $collection = $candidate['collection'];
            $pathInfo   = $candidate['pathInfo'];
            if ($pathInfo['remainder'] === '') {
                continue;
            }

            $entry = $entryService->getBySlug(
                $lang,
                (string) ($collection['collection_key'] ?? ''),
                $pathInfo['remainder'],
                $preview,
                $previewExpires,
                $previewSig
            );

            if ($entry) {
                return $this->renderEntry($entry, $collection, $lang);
            }
        }

        // Step 4: Support CMS pages that host collection listings directly under their slug, e.g. /es/festivales/{slug}.
        $pathSegments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        if (count($pathSegments) > 1) {
            $pageSlug = array_shift($pathSegments);
            $entrySlug = implode('/', $pathSegments);

            if ($pageSlug !== '' && $entrySlug !== '') {
                $page = $pageService->getBySlug($lang, $pageSlug, $preview, $previewExpires, $previewSig);
                if ($page) {
                    $collectionId = $this->resolveCollectionIdFromBlocks($page['blocks'] ?? []);
                    if ($collectionId > 0) {
                        $collection = $this->resolveCollectionById($collectionId, $lang, $collections);
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

        // Step 5: Fallback collection index page (e.g. /es/festivales when no dedicated CMS page exists).
        foreach ($collectionCandidates as $candidate) {
            if ($candidate['pathInfo']['remainder'] === '') {
                return $this->renderFallbackCollectionIndex($candidate['collection'], $lang);
            }
        }

        // Step 6: 404
        return $this->notFound("No se encontró la página: {$path}");
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

        $blockContext = [
            'featured_image_url' => $featuredImageUrl,
            'collection_key' => (string) ($collection['collection_key'] ?? ''),
        ];
        $entryBlocks = $entry['blocks'] ?? [];

        // Resolve all dynamic block data through one page-level prefetch pass.
        $composition = $this->composePageContext($entryBlocks, $lang, $blockContext);
        $renderContext = array_merge($blockContext, $composition['block_context']);

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
            'renderedBlocks'      => $blockRenderer->render($entryBlocks, $lang, $renderContext),
            'localized_urls'      => $this->resolveEntryLocalizedUrls($collection, $entry, $lang, $resolvedSlug),
            '__layout_data'       => $composition['layout'],
            'cacheScopes'         => array_values(array_unique(array_merge(
                ['entries', 'pages', 'settings', 'menus'],
                is_array($renderContext['cacheScopes'] ?? null) ? $renderContext['cacheScopes'] : [],
            ))),
        ];

        return $this->render('collection/show', $data);
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
     * Build an in-memory prefix index from the cached collection list. The
     * expensive operation is the remote entry lookup, so only candidates
     * whose canonical prefix matches the request are returned.
     *
     * @param array<mixed> $collections
     * @return list<array{collection: array<string, mixed>, pathInfo: array{prefix: string, remainder: string}}>
     */
    private function collectionCandidatesForPath(array $collections, string $path): array
    {
        $normalizedPath = trim($path, '/');
        if ($normalizedPath === '') {
            return [];
        }

        $firstSegment = explode('/', $normalizedPath, 2)[0];
        $prefixIndex  = [];

        foreach ($collections as $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $prefix = trim(collection_url_path($collection), '/');
            if ($prefix === '') {
                continue;
            }

            $prefixFirstSegment = explode('/', $prefix, 2)[0];
            $prefixIndex[$prefixFirstSegment][] = $collection;
        }

        $candidates = [];
        foreach ($prefixIndex[$firstSegment] ?? [] as $collection) {
            $pathInfo = collection_url_path_info($collection, $normalizedPath);
            if ($pathInfo !== null) {
                $candidates[] = [
                    'collection' => $collection,
                    'pathInfo'   => $pathInfo,
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param array<mixed>|null $collections
     * @return array<string, mixed>|null
     */
    private function resolveCollectionById(int $collectionId, string $lang, ?array $collections = null): ?array
    {
        $collections ??= Services::siteCollectionService()->getAll($lang);

        foreach ($collections as $collection) {
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

    /**
     * Synthesize and render a fallback listing page for a collection that lacks a dedicated CMS index page.
     *
     * @param array<string, mixed> $collection
     */
    private function renderFallbackCollectionIndex(array $collection, string $lang): ResponseInterface
    {
        $collectionTitle = collection_display_title($collection);
        $collectionIntro = collection_display_intro($collection);
        $collectionKey   = (string) ($collection['collection_key'] ?? '');

        $pageData = [
            'title'           => $collectionTitle,
            'excerpt'         => $collectionIntro,
            'showPageHeading' => true,
            'pageTitle'       => $collectionTitle,
            'metaDescription' => $collectionIntro,
            'canonicalUrl'    => site_url('/' . $lang . collection_url_path($collection)),
            'ogImage'         => '',
            'metaRobots'      => 'index, follow',
            'schemaData'      => null,
            'localized_urls'  => localized_collection_urls($collection),
        ];

        $blocks = [
            [
                'block_key'    => 'collection_listing',
                'block_config' => [
                    'collection_id'   => (int) ($collection['id'] ?? 0),
                    'collection_key'  => $collectionKey,
                    'items_limit'     => 12,
                    'order_by'        => 'published_at',
                    'order_direction' => 'desc',
                    'layout_variant'  => 'cards',
                ],
                'block_data' => [],
                'children'   => [],
            ],
        ];

        return $this->renderPageWithBlocks($pageData, $blocks, $lang);
    }

    /**
     * Check if a CMS page payload returned by the domain API actually matches the requested slug exactly.
     *
     * @param array<string, mixed> $page
     */
    private function isExactPageSlugMatch(array $page, string $expectedPath, string $lang): bool
    {
        $expectedPath = trim($expectedPath, '/');
        if ($expectedPath === '') {
            return true;
        }

        $translation = $this->resolvePageTranslation($page, $lang);
        $slug = trim((string) ($translation['slug'] ?? $page['slug'] ?? ''), '/');

        if ($slug === '') {
            return false;
        }

        return strcasecmp($slug, $expectedPath) === 0;
    }
}
