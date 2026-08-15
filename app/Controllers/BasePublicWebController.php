<?php

declare(strict_types=1);

namespace App\Controllers;

use App\PageDelivery\PageDeliveryResponse;
use App\PageDelivery\PublicSnapshotManifest;
use App\Support\RequestContext;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

abstract class BasePublicWebController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        $cacheScopes = $this->normalizeCacheScopes($data['cacheScopes'] ?? []);
        unset($data['cacheScopes']);
        $this->finishRouteResolution();
        $preview = $this->request->getGet('preview') === '1';
        $pageCacheTtl = config('App')->webPageCacheTtl;
        $cacheable = ! $preview && $pageCacheTtl > 0;
        if (! $preview && $pageCacheTtl > 0) {
            // CodeIgniter resets ResponseCache's TTL at the start of every
            // request. Set it only for rendered public HTML responses; form
            // posts, redirects and preview requests stay uncached.
            $this->cachePage($pageCacheTtl);
        }

        $data['view'] = $view;

        if (empty($data['canonicalUrl'])) {
            $data['canonicalUrl'] = site_url($this->request->getPath());
        }

        // PageDelivery supplies layout data before rendering. Error and form
        // views may render without a delivery envelope, so they receive a
        // deterministic empty layout instead of reopening domain reads.
        $prefetchedLayout = $data['__layout_data'] ?? null;
        unset($data['__layout_data']);
        if (is_array($prefetchedLayout)) {
            $data = array_merge($data, $prefetchedLayout);
        } else {
            $data = array_merge([
                'mainMenu' => ['items' => []],
                'footerMenu' => ['items' => []],
                'legalMenu' => ['items' => []],
                'settings' => [],
                'socialLinks' => [],
            ], $data);
        }

        // Snapshots may contain menu URLs normalized by an older runtime (for
        // example `/` for the Spanish homepage). Re-apply the current public
        // slug policy at the render boundary so a stale layout cannot publish
        // a redirecting homepage link.
        foreach (['mainMenu', 'footerMenu', 'legalMenu'] as $menuKey) {
            if (is_array($data[$menuKey] ?? null)) {
                $data[$menuKey] = $this->normalizeMenuUrls($data[$menuKey], (string) $this->request->getLocale());
            }
        }

        if (! array_key_exists('schemaData', $data)) {
            $data['schemaData'] = null;
        }

        // layouts/public.php forwards the full page data to nested partials
        // (head, $view) as a single $data variable, so it needs it under its
        // own 'data' key explicitly — it must not rely on Config\View's
        // saveData persistence to leak it in as a side effect.
        $data['data'] = $data;

        // saveData:false — Config\View::$saveData defaults to true and would
        // otherwise persist this render's data into the shared view store for
        // the rest of the process (e.g. across PHPUnit test cases).
        $body = RequestContext::measurePhase(
            'view_render',
            fn (): string => view('layouts/public', $data, ['saveData' => false]),
        );
        if ($cacheable) {
            try {
                $cacheKey = service('responsecache')->generateCacheKey($this->request);
                \Config\Services::htmlResponseCacheRegistry()->record(
                    $this->request->getUri()->getPath(),
                    (string) $this->request->getLocale(),
                    array_values(array_filter(array_map('strval', $cacheScopes))),
                    $cacheKey,
                );
            } catch (\Throwable $exception) {
                // The registry is an invalidation accelerator; failure to
                // write it must never turn an otherwise valid page into 500.
                log_message('warning', 'HTML response-cache registry skipped: {message}', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }
        $etag = '"' . sha1($body) . '"';
        $cacheControl = $cacheable
            ? sprintf('public, max-age=%d, stale-while-revalidate=60', $pageCacheTtl)
            : 'no-store, private';

        return $this->response
            // cachePage() and PHP/session defaults may have already added
            // cache headers. Remove them before setting the single policy for
            // this response; concatenating `no-store` with `public` makes the
            // resulting header contradictory and defeats public caching.
            ->removeHeader('Cache-Control')
            ->removeHeader('Pragma')
            ->removeHeader('Expires')
            ->setHeader('Cache-Control', $cacheControl)
            ->setHeader('ETag', $etag)
            ->setHeader('Vary', 'Accept-Language')
            ->setBody($body);
    }

    /**
     * Normalize cached menu URLs without touching external editorial links.
     *
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private function normalizeMenuUrls(array $menu, string $locale): array
    {
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $candidate = is_scalar($item['custom_url'] ?? null)
                ? (string) $item['custom_url']
                : '';
            $normalized = \App\Support\PublicPaths::normalizeLocalizedPath($candidate, $locale);
            if ($normalized !== null) {
                $item['custom_url'] = $normalized;
            }

            if (is_array($item['children'] ?? null)) {
                $item['children'] = $this->normalizeMenuUrls(
                    ['items' => $item['children']],
                    $locale,
                )['items'];
            }
        }
        unset($item);

        $menu['items'] = $items;

        return $menu;
    }

    /**
     * Reads the preview query params off the incoming request and forwards
     * them opaquely — this app never validates the signature itself, only
     * Domain does (PreviewToken::verify).
     *
     * @return array{0: bool, 1: ?string, 2: ?string}
     */
    protected function resolvePreviewParams(): array
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
     * Render a page already composed by PageDelivery. No API or cache reads are
     * performed between this method and the view renderer.
     */
    protected function renderDeliveredPage(PageDeliveryResponse $delivery, string $lang): ResponseInterface
    {
        $this->finishRouteResolution();

        // A redirect ends the request before any page is rendered — it must not emit a
        // page_render_phase event (RequestContext::pageRenderSummary() stays
        // null while setPageDelivery() is never called for this response).
        if ($delivery->isRedirect()) {
            $target = (string) $delivery->meta['redirect_to'];

            return redirect()->to(lang_url($target, $lang))->setStatusCode($delivery->status);
        }

        RequestContext::setPageDelivery([
            'available' => $delivery->isAvailable(),
            'status' => $delivery->status,
            'cache' => $delivery->meta['cache'] ?? null,
            'state' => $delivery->source['state'] ?? null,
            'stale' => $delivery->source['stale'] ?? false,
        ]);

        if (! $delivery->isAvailable() || $delivery->page === null) {
            if ($delivery->status === 404) {
                $message = ($delivery->meta['route'] ?? null) === 'home'
                    ? 'Página de inicio no encontrada'
                    : 'Página no encontrada';

                return $this->notFound($message);
            }

            return $this->response
                ->removeHeader('Cache-Control')
                ->setStatusCode($delivery->status >= 500 ? $delivery->status : 503)
                ->setHeader('Cache-Control', 'no-store, private')
                ->setBody('Public page delivery is temporarily unavailable.');
        }

        if (($delivery->page['page_type'] ?? null) === 'collection_entry') {
            return $this->renderDeliveredCollectionEntry($delivery, $lang);
        }

        $renderContext = $delivery->blockContext;
        $renderContext['settings'] = is_array($delivery->layout['settings'] ?? null)
            ? $delivery->layout['settings']
            : [];
        $data = ($delivery->page['page_type'] ?? null) === 'collection_fallback_index'
            ? $this->fallbackPageData($delivery->page, $lang, $renderContext)
            : $this->cmsPageData($delivery->page, $lang, $renderContext);
        $data['__layout_data'] = $delivery->layout;
        $data['pageDelivery'] = $delivery->meta;
        $data['cacheScopes'] = $this->normalizeCacheScopes(array_merge(
            ['pages', 'settings', 'menus'],
            is_array($renderContext['cacheScopes'] ?? null) ? $renderContext['cacheScopes'] : [],
            isset($renderContext['event_item']) ? ['events'] : [],
            isset($renderContext['catalog_item']) ? ['collection_items'] : [],
        ));

        return $this->render('page', $data);
    }

    /**
     * Render an entry resolved by the BFF without reopening Web-side reads.
     * PageController supplies the entry-specific presentation because the
     * public entry view differs from a CMS page view.
     */
    protected function renderDeliveredCollectionEntry(PageDeliveryResponse $delivery, string $lang): ResponseInterface
    {
        return $this->response
            ->removeHeader('Cache-Control')
            ->setStatusCode(503)
            ->setHeader('Cache-Control', 'no-store, private')
            ->setBody('Public collection entry delivery is temporarily unavailable.');
    }

    /**
     * Normalize the BFF's synthetic collection-index contract to the standard
     * page view model. Fallback pages intentionally have no CMS translation.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function fallbackPageData(array $page, string $lang, array $context): array
    {
        $title = (string) ($page['title'] ?? '');
        $excerpt = (string) ($page['excerpt'] ?? '');
        $schemaData = $page['schemaData'] ?? null;
        if (is_string($schemaData) && trim($schemaData) !== '') {
            $schemaData = json_decode($schemaData, true);
        } elseif (! is_array($schemaData)) {
            $schemaData = null;
        }

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'showPageHeading' => (bool) ($page['showPageHeading'] ?? true),
            'pageTitle' => (string) ($page['pageTitle'] ?? $title),
            'metaDescription' => (string) ($page['metaDescription'] ?? $excerpt),
            'canonicalUrl' => (string) ($page['canonicalUrl'] ?? site_url($this->request->getPath())),
            'ogImage' => (string) ($page['ogImage'] ?? ''),
            'metaRobots' => (string) ($page['metaRobots'] ?? 'index, follow'),
            'schemaData' => $schemaData,
            'renderedBlocks' => Services::blockRenderer()->render($this->pageBlocks($page), $lang, $context),
            'localized_urls' => is_array($page['localized_urls'] ?? null) ? $page['localized_urls'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function cmsPageData(array $page, string $lang, array $context): array
    {
        $translation = $this->resolvePageTranslation($page, $lang);
        $blocks = $this->pageBlocks($page);
        $showPageHeading = array_key_exists('showPageHeading', $page)
            ? (bool) $page['showPageHeading']
            : ! $this->pageHasHeroHeading($blocks);

        $slug = trim((string) ($translation['slug'] ?? ''), '/');
        $isHomepage = \App\Support\PublicPaths::isHomepageSlug($slug, $lang)
            || (string) ($page['page_type'] ?? '') === 'home';
        $canonicalUrl = (string) ($translation['canonical_url'] ?? '');
        if ($canonicalUrl === '') {
            if ($isHomepage || $slug === '') {
                $canonicalUrl = site_url('/' . $lang . \App\Support\PublicPaths::homepagePath($lang));
            } else {
                $canonicalUrl = site_url('/' . $lang . '/' . $slug);
            }
        }

        $routeKey = $this->domainRouteKey($page);
        if ($routeKey !== null) {
            $canonicalUrl = lang_url(\App\Support\PublicPaths::routePath($routeKey, $lang), $lang);
        } elseif ($isHomepage) {
            // The CMS page type is internally `home`, but the public slug is
            // locale-specific and must remain visible in canonical URLs.
            $canonicalUrl = site_url('/' . $lang . \App\Support\PublicPaths::homepagePath($lang));
        }

        $ogImage = $translation['og_image'] ?? null;
        $ogImageUrl = is_array($ogImage) ? (string) ($ogImage['url'] ?? '') : (is_string($ogImage) ? trim($ogImage) : '');

        $schemaData = $translation['schema_data'] ?? null;
        if (is_string($schemaData) && trim($schemaData) !== '') {
            $schemaData = json_decode($schemaData, true);
        } elseif (! is_array($schemaData)) {
            $schemaData = null;
        }

        return [
            'title' => (string) ($translation['title'] ?? ''),
            'excerpt' => (string) ($translation['excerpt'] ?? ''),
            'showPageHeading' => $showPageHeading,
            'pageTitle' => (isset($translation['meta_title']) && trim((string) $translation['meta_title']) !== '')
                ? (string) $translation['meta_title']
                : (string) ($translation['title'] ?? ''),
            'metaDescription' => (isset($translation['meta_description']) && trim((string) $translation['meta_description']) !== '')
                ? (string) $translation['meta_description']
                : (string) ($translation['excerpt'] ?? ''),
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImageUrl,
            'metaRobots' => (isset($translation['robots']) && trim((string) $translation['robots']) !== '')
                ? (string) $translation['robots']
                : 'index, follow',
            'schemaData' => $schemaData,
            'renderedBlocks' => \Config\Services::blockRenderer()->render($blocks, $lang, $context),
            'localized_urls' => $this->resolveLocalizedPageUrls($page, $lang),
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function pageBlocks(array $page): array
    {
        return is_array($page['blocks'] ?? null)
            ? array_values(array_filter(
                $page['blocks'],
                static fn (mixed $block): bool => is_array($block),
            ))
            : [];
    }

    protected function notFound(string $message = 'Página no encontrada'): ResponseInterface
    {
        return $this->render('errors/404', ['message' => $message])
            ->setStatusCode(404);
    }

    /** Start measuring the route-resolution part of a public page request. */
    protected function beginRouteResolution(): void
    {
        RequestContext::startPhase('route_resolution');
    }

    /** Stop route timing immediately before page composition begins. */
    protected function finishRouteResolution(): void
    {
        RequestContext::stopPhase('route_resolution');
    }

    /**
     * Deliver a public route through the BFF. Snapshot eligibility remains
     * controlled by the manifest, while every public route uses the same BFF
     * page-resolve contract.
     */
    protected function deliverBffPageRoute(
        string $lang,
        string $route,
        bool $preview = false,
        ?string $previewExpires = null,
        ?string $previewSignature = null,
    ): ResponseInterface {
        $query = $this->request->getGet();
        $query = is_array($query) ? $query : [];
        $request = (new PublicSnapshotManifest())->requestForBff(
            locale: $lang,
            route: $route,
            preview: $preview,
            previewExpires: $previewExpires,
            previewSignature: $previewSignature,
            query: $query,
        );
        if ($request === null) {
            return $this->response
                ->removeHeader('Cache-Control')
                ->setStatusCode(503)
                ->setHeader('Cache-Control', 'no-store, private')
                ->setBody('Public page delivery is temporarily unavailable.');
        }

        return $this->renderDeliveredPage(Services::pageDelivery()->deliver($request), $lang);
    }

    protected function deliverPublicRoute(string $route): ResponseInterface
    {
        $lang = $this->request->getLocale();
        $this->beginRouteResolution();
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();

        return $this->deliverBffPageRoute($lang, $route, $preview, $previewExpires, $previewSig);
    }

    /**
     * @param mixed $scopes
     * @return list<string>
     */
    private function normalizeCacheScopes(mixed $scopes): array
    {
        $scopes = is_array($scopes) ? $scopes : [];
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $scope): string => is_scalar($scope) ? strtolower(trim((string) $scope)) : '',
            $scopes,
        ))));

        return $normalized !== [] ? $normalized : ['pages', 'settings', 'menus'];
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    protected function resolvePageTranslation(array $page, string $lang): array
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];

        if (isset($page['title'])) {
            $translation = $page;

            foreach ($translations as $trans) {
                if (! is_array($trans)) {
                    continue;
                }

                if ($this->translationMatchesLocale($trans, $lang)) {
                    $translation = array_merge($translation, $trans);
                    break;
                }
            }

            return $this->withLocalizedSlug($translation, $page, $lang);
        }

        foreach ($translations as $trans) {
            if (is_array($trans) && $this->translationMatchesLocale($trans, $lang)) {
                return $this->withLocalizedSlug($trans, $page, $lang);
            }
        }

        $fallback = is_array($translations[0] ?? null) ? $translations[0] : [];

        return $this->withLocalizedSlug($fallback, $page, $lang);
    }

    /**
     * Public-read page payloads expose localized slugs separately from the
     * translated content. Normalize that compact contract for renderers and
     * the dynamic resolver, which both consume a translation-shaped `slug`.
     *
     * @param array<string, mixed> $translation
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function withLocalizedSlug(array $translation, array $page, string $lang): array
    {
        if (trim((string) ($translation['slug'] ?? '')) !== '') {
            return $translation;
        }

        $localizedSlugs = is_array($translation['localized_slugs'] ?? null)
            ? $translation['localized_slugs']
            : (is_array($page['localized_slugs'] ?? null) ? $page['localized_slugs'] : []);
        $localizedSlug = trim((string) ($localizedSlugs[$lang] ?? ''));

        if ($localizedSlug !== '') {
            $translation['slug'] = $localizedSlug;
        }

        return $translation;
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, string>
     */
    private function resolveLocalizedPageUrls(array $page, string $lang): array
    {
        $routeKey = $this->domainRouteKey($page);
        if ($routeKey !== null) {
            $localizedUrls = [];
            foreach (config('App')->supportedLocales as $locale) {
                $path = \App\Support\PublicPaths::routePath($routeKey, $locale);
                if ($path !== null) {
                    $localizedUrls[$locale] = lang_url($path, $locale);
                }
            }

            return $localizedUrls;
        }

        $localizedUrls = [];
        $localizedSlugs = is_array($page['localized_slugs'] ?? null) ? $page['localized_slugs'] : [];
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $isHomepage = (string) ($page['page_type'] ?? '') === 'home';

        foreach (config('App')->supportedLocales as $locale) {
            $slug = trim((string) ($localizedSlugs[$locale] ?? ''), '/');

            if ($slug === '') {
                foreach ($translations as $trans) {
                    if (! is_array($trans) || ! $this->translationMatchesLocale($trans, $locale)) {
                        continue;
                    }

                    $slug = trim((string) ($trans['slug'] ?? ''), '/');
                    break;
                }
            }

            if ($slug === '') {
                continue;
            }

            if ($isHomepage || \App\Support\PublicPaths::isHomepageSlug($slug, $locale)) {
                $localizedUrls[$locale] = site_url('/' . $locale . \App\Support\PublicPaths::homepagePath($locale));
                continue;
            }

            $localizedUrls[$locale] = site_url('/' . $locale . '/' . ltrim($slug, '/'));
        }

        return $localizedUrls;
    }

    /** @param array<string, mixed> $page */
    private function domainRouteKey(array $page): ?string
    {
        return match ((string) ($page['page_type'] ?? '')) {
            'events' => 'events',
            'catalog_listing' => 'catalog',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $translation
     */
    private function translationMatchesLocale(array $translation, string $locale): bool
    {
        return (string) ($translation['language_code'] ?? $translation['code'] ?? '') === $locale
            || (string) ($translation['lang'] ?? '') === $locale
            || (string) ($translation['locale'] ?? '') === $locale;
    }

    /**
     * @param array<mixed> $blocks
     */
    private function pageHasHeroHeading(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (in_array((string) ($block['block_key'] ?? ''), ['hero_slider', 'hero_banner', 'page_header'], true)) {
                return true;
            }
        }

        return false;
    }
}
