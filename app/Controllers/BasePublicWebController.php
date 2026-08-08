<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

abstract class BasePublicWebController extends BaseController
{
    /** @param array<string,mixed> $data */
    protected function render(string $view, array $data = []): ResponseInterface
    {
        $data['view'] = $view;

        if (empty($data['canonicalUrl'])) {
            $data['canonicalUrl'] = site_url($this->request->getPath());
        }

        // Pre-load global layout data: menus and settings (parallelized via multiGet)
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

        return $this->response
            ->setHeader('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
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
        $translation = $this->resolvePageTranslation($page, $lang);
        $blocks = is_array($page['blocks'] ?? null)
            ? array_values(array_filter(
                $page['blocks'],
                static fn (mixed $block): bool => is_array($block)
            ))
            : [];
        $showPageHeading = array_key_exists('showPageHeading', $page)
            ? (bool) $page['showPageHeading']
            : ! $this->pageHasHeroHeading($blocks);

        $canonicalUrl = (string) ($translation['canonical_url'] ?? '');
        if ($canonicalUrl === '') {
            $slug = trim((string) ($translation['slug'] ?? ''), '/');
            if ($slug === '' || $slug === 'home') {
                $canonicalUrl = site_url('/' . $lang);
            } else {
                $canonicalUrl = site_url('/' . $lang . '/' . $slug);
            }
        }

        $routeKey = $this->domainRouteKey($page);
        if ($routeKey !== null) {
            $canonicalUrl = lang_url(\App\Support\PublicPaths::routePath($routeKey, $lang), $lang);
        }

        $ogImage = $translation['og_image'] ?? null;
        $ogImageUrl = is_array($ogImage) ? (string) ($ogImage['url'] ?? '') : (is_string($ogImage) ? trim($ogImage) : '');

        $schemaData = $translation['schema_data'] ?? null;
        if (is_string($schemaData) && trim($schemaData) !== '') {
            $schemaData = json_decode($schemaData, true);
        } elseif (! is_array($schemaData)) {
            $schemaData = null;
        }

        // Smart prefetch: analyze block requirements and load data in parallel
        $prefetchedData = [];
        if (!empty($blocks)) {
            $blockAnalyzer = \Config\Services::blockAnalyzerService();
            $requirements = $blockAnalyzer->analyze($blocks, $lang);

            if (!empty($requirements)) {
                $smartPrefetch = \Config\Services::smartPrefetchService();
                $prefetchedData = $smartPrefetch->prefetch($requirements, $lang);
            }
        }

        // Merge prefetched data into context for block rendering
        $renderContext = array_merge($context, $prefetchedData);

        $data = [
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
            'renderedBlocks' => \Config\Services::blockRenderer()->render($blocks, $lang, $renderContext),
            'localized_urls' => $this->resolveLocalizedPageUrls($page, $lang),
        ];

        return $this->render('page', $data);
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

        $pageData['renderedBlocks'] = \Config\Services::blockRenderer()->render($page['blocks'] ?? [], $lang, $context);

        return $this->render('page', $pageData);
    }

    protected function notFound(string $message = 'Página no encontrada'): ResponseInterface
    {
        return $this->render('errors/404', ['message' => $message])
            ->setStatusCode(404);
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

            return $translation;
        }

        foreach ($translations as $trans) {
            if (is_array($trans) && $this->translationMatchesLocale($trans, $lang)) {
                return $trans;
            }
        }

        return is_array($translations[0] ?? null) ? $translations[0] : [];
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

            if ($slug === 'home') {
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
