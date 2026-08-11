<?php

declare(strict_types=1);

namespace App\Controllers;

use App\PageDelivery\PageDeliveryResponse;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BasePublicWebController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        $preview = $this->request->getGet('preview') === '1';
        $pageCacheTtl = config('App')->webPageCacheTtl;
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

        // PageDelivery supplies layout data before rendering. Other legacy
        // controllers keep the same pre-render layout fallback.
        $prefetchedLayout = $data['__layout_data'] ?? null;
        unset($data['__layout_data']);
        if (is_array($prefetchedLayout)) {
            $data = array_merge($data, $prefetchedLayout);
        } else {
            try {
                $layoutData = \Config\Services::layoutDataPrefetchService()->prefetchLayoutData($data);
                $data = array_merge($data, $layoutData);
            } catch (\Throwable) {
                // Fallback to empty data if prefetch fails
                if (! isset($data['mainMenu'])) {
                    $data['mainMenu'] = ['items' => []];
                }
                if (! isset($data['footerMenu'])) {
                    $data['footerMenu'] = ['items' => []];
                }
                if (! isset($data['legalMenu'])) {
                    $data['legalMenu'] = ['items' => []];
                }
                if (! isset($data['settings'])) {
                    $data['settings'] = [];
                }
                if (! isset($data['socialLinks'])) {
                    $data['socialLinks'] = [];
                }
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
        $body = view('layouts/public', $data, ['saveData' => false]);
        $etag = '"' . sha1($body) . '"';
        $cacheable = ! $preview && $pageCacheTtl > 0;
        $cacheControl = $cacheable
            ? sprintf('public, max-age=%d, stale-while-revalidate=60', $pageCacheTtl)
            : 'no-store, private';

        return $this->response
            ->setHeader('Cache-Control', $cacheControl)
            ->setHeader('ETag', $etag)
            ->setHeader('Vary', 'Accept-Language')
            ->setBody($body);
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
     * Render a standard public page that is composed from one or more blocks.
     *
     * @param array<string, mixed> $pageData
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed> $context
     */
    protected function renderPageWithBlocks(array $pageData, array $blocks, string $lang, array $context = []): ResponseInterface
    {
        $context = array_merge($context, $this->prefetchBlockContext($blocks, $lang));
        $pageData['renderedBlocks'] = \Config\Services::blockRenderer()->render($blocks, $lang, $context);

        return $this->render('page', $pageData);
    }

    /**
     * Render a CMS-owned public page with consistent metadata and localized URLs.
     *
     * @param array<string, mixed> $page
     * @param array<string, mixed> $context
     */
    protected function renderCmsPage(array $page, string $lang, array $context = []): ResponseInterface
    {
        $context = array_merge($context, $this->prefetchBlockContext($this->pageBlocks($page), $lang));
        $data = $this->cmsPageData($page, $lang, $context);

        return $this->render('page', $data);
    }

    /**
     * Render a page already composed by PageDelivery. No API or cache reads are
     * performed between this method and the view renderer.
     */
    protected function renderDeliveredPage(PageDeliveryResponse $delivery, string $lang): ResponseInterface
    {
        if (! $delivery->isAvailable() || $delivery->page === null) {
            if ($delivery->status === 404) {
                return $this->notFound('Página de inicio no encontrada');
            }

            return $this->response
                ->setStatusCode($delivery->status >= 500 ? $delivery->status : 503)
                ->setHeader('Cache-Control', 'no-store, private')
                ->setBody('Public page delivery is temporarily unavailable.');
        }

        $renderContext = $delivery->blockContext;
        $renderContext['settings'] = is_array($delivery->layout['settings'] ?? null)
            ? $delivery->layout['settings']
            : [];
        $data = $this->cmsPageData($delivery->page, $lang, $renderContext);
        $data['__layout_data'] = $delivery->layout;
        $data['pageDelivery'] = $delivery->meta;

        return $this->render('page', $data);
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
        $isHomepage = in_array(strtolower($slug), ['home', 'inicio'], true)
            || (string) ($page['page_type'] ?? '') === 'home';
        $canonicalUrl = (string) ($translation['canonical_url'] ?? '');
        if ($canonicalUrl === '') {
            if ($isHomepage || $slug === '') {
                $canonicalUrl = site_url('/' . $lang);
            } else {
                $canonicalUrl = site_url('/' . $lang . '/' . $slug);
            }
        }

        $routeKey = $this->domainRouteKey($page);
        if ($routeKey !== null) {
            $canonicalUrl = lang_url(\App\Support\PublicPaths::routePath($routeKey, $lang), $lang);
        } elseif ($isHomepage) {
            // `home`/`inicio` are aliases for the localized root. The CMS may
            // retain an old canonical_url, but SEO links must not point at a
            // URL that immediately redirects back to the root.
            $canonicalUrl = site_url('/' . $lang);
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

    /**
     * Resolve a CMS page first and fall back to a generated listing page when absent.
     *
     * @param callable(string): array{page: array<string, mixed>, blocks: list<array<string, mixed>>} $fallbackBuilder
     * @param array<string, mixed> $context
     */
    protected function renderCmsPageOrFallbackListing(
        string $lang,
        string $slug,
        callable $fallbackBuilder,
        array $context = []
    ): ResponseInterface {
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();
        $page = \Config\Services::sitePageService()->getBySlug($lang, $slug, $preview, $previewExpires, $previewSig);

        if (is_array($page)) {
            return $this->renderCmsPage($page, $lang, $context);
        }

        $listing = $fallbackBuilder($lang);
        if (! is_array($listing)) {
            return $this->notFound();
        }

        $pageData = $listing['page'] ?? null;
        $blocks = $listing['blocks'] ?? null;

        if (! is_array($pageData) || ! is_array($blocks)) {
            return $this->notFound();
        }

        return $this->renderPageWithBlocks($pageData, $blocks, $lang, $context);
    }

    /**
     * Render a CMS-backed template page using a dedicated type and runtime context.
     *
     * @param array<string, mixed> $pageData
     * @param array<string, mixed> $context
     */
    protected function renderTemplatePage(
        string $templateType,
        string $lang,
        array $pageData,
        array $context = []
    ): ResponseInterface {
        $page = \Config\Services::sitePageService()->getByType($lang, $templateType);

        if (! is_array($page)) {
            log_message('error', "Template page not found for type: {$templateType} in lang: {$lang}");
            return $this->notFound();
        }

        $blocks = is_array($page['blocks'] ?? null)
            ? array_values(array_filter(
                $page['blocks'],
                static fn (mixed $block): bool => is_array($block),
            ))
            : [];
        $context = array_merge($context, $this->prefetchBlockContext($blocks, $lang));
        $pageData['renderedBlocks'] = \Config\Services::blockRenderer()->render($blocks, $lang, $context);

        return $this->render('page', $pageData);
    }

    protected function notFound(string $message = 'Página no encontrada'): ResponseInterface
    {
        return $this->render('errors/404', ['message' => $message])
            ->setStatusCode(404);
    }

    /**
     * Resolve dynamic blocks before ViewModels render the page.
     *
     * The public page must remain renderable if a domain is unavailable; the
     * individual ViewModels already handle an empty prefetched result.
     *
     * @param list<array<string, mixed>> $blocks
     * @return array<string, mixed>
     */
    protected function prefetchBlockContext(array $blocks, string $lang): array
    {
        try {
            return \Config\Services::blockPrefetchService()->prefetchContext($blocks, $lang);
        } catch (\Throwable) {
            // Mark the prefetch phase as complete even on an internal planner
            // failure so ViewModels render an explicit empty state instead of
            // reopening the old per-block HTTP fallback path.
            return [
                'block_prefetch' => [],
                'block_prefetch_complete' => true,
            ];
        }
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

            if ($isHomepage || in_array(strtolower($slug), ['home', 'inicio'], true)) {
                $localizedUrls[$locale] = site_url('/' . $locale);
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
