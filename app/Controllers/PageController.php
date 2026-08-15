<?php

declare(strict_types=1);

namespace App\Controllers;

use App\PageDelivery\PageDeliveryResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Public page controller. Route resolution and composition belong to the BFF;
 * this controller only applies URL policy and maps the delivery contract to
 * the presentation views.
 */
class PageController extends BasePublicWebController
{
    /** Enforce the locale prefix in the URL. */
    protected function enforceLocale(): ?ResponseInterface
    {
        $request = service('request');
        $uri = $request->getUri();
        $firstSegment = strtolower(trim((string) $uri->getSegment(1)));
        $supportedLocales = config('App')->supportedLocales;

        if (in_array($firstSegment, $supportedLocales, true)) {
            $request->setLocale($firstSegment);
        }

        if (! in_array($firstSegment, $supportedLocales, true)) {
            $locale = $request->getLocale();
            $path = implode('/', $uri->getSegments());
            $query = $uri->getQuery();
            $target = '/' . $locale . ($path !== '' ? '/' . $path : '') . ($query !== '' ? '?' . $query : '');

            return redirect()->to($target)->setStatusCode(302);
        }

        return null;
    }

    public function home(): ResponseInterface
    {
        $this->beginRouteResolution();
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();

        return $this->deliverBffPageRoute($lang, 'home', $preview, $previewExpires, $previewSig);
    }

    /** Resolve every public path through the BFF page-resolve contract. */
    public function resolve(string ...$segments): ResponseInterface
    {
        $this->beginRouteResolution();
        if ($redirect = $this->enforceLocale()) {
            return $redirect;
        }

        $lang = service('request')->getLocale();
        $path = trim(implode('/', $segments), '/');
        [$preview, $previewExpires, $previewSig] = $this->resolvePreviewParams();

        if ($path === '') {
            return $this->home();
        }

        if (strcasecmp($path, 'public/' . $lang) === 0) {
            return redirect()
                ->to(lang_url(\App\Support\PublicPaths::homepagePath($lang), $lang))
                ->setStatusCode(301);
        }

        if (\App\Support\PublicPaths::isHomepageSlug($path, $lang)) {
            $canonicalPath = \App\Support\PublicPaths::homepagePath($lang);
            if (trim($path, '/') !== trim($canonicalPath, '/')) {
                return redirect()->to(lang_url($canonicalPath, $lang))->setStatusCode(301);
            }

            return $this->deliverBffPageRoute($lang, 'home', $preview, $previewExpires, $previewSig);
        }

        return $this->deliverBffPageRoute($lang, $path, $preview, $previewExpires, $previewSig);
    }

    protected function renderDeliveredCollectionEntry(PageDeliveryResponse $delivery, string $lang): ResponseInterface
    {
        $entry = $delivery->page ?? [];
        $collection = is_array($entry['collection'] ?? null) ? $entry['collection'] : [];
        $relatedEntries = is_array($entry['related_entries'] ?? null) ? $entry['related_entries'] : [];
        $translation = $this->getEntryTranslation($entry, $lang);
        $resolvedSlug = trim((string) ($translation['slug'] ?? ''), '/');
        if ($resolvedSlug === '') {
            $localizedSlugs = is_array($entry['localized_slugs'] ?? null) ? $entry['localized_slugs'] : [];
            $resolvedSlug = trim((string) ($localizedSlugs[$lang] ?? ''), '/');
        }

        $collectionUrlPath = collection_url_path($collection);
        if ($collectionUrlPath === '') {
            $collectionUrlPath = $this->currentCollectionPathFromRequest();
        }

        $canonicalUrl = trim((string) ($translation['canonical_url'] ?? ''));
        if ($canonicalUrl === '') {
            $canonicalUrl = site_url('/' . $lang . $collectionUrlPath . '/' . ltrim($resolvedSlug, '/'));
        }

        $featuredImage = is_array($entry['featured_image'] ?? null) ? $entry['featured_image'] : [];
        $featuredImageUrl = trim((string) ($featuredImage['url'] ?? ''));
        $ogImage = is_array($translation['og_image'] ?? null) ? $translation['og_image'] : [];
        $ogImageUrl = trim((string) ($ogImage['url'] ?? '')) ?: $featuredImageUrl;
        $blocks = $this->entryBlocks($entry);
        $hasHeroHeading = $this->containsBlock($blocks, ['hero_slider', 'hero_banner', 'page_header']);
        $hasHeroImage = $this->containsBlock($blocks, ['hero_slider', 'hero_banner']);

        if ($featuredImageUrl !== '' && ! $hasHeroImage) {
            Services::blockRenderer()->addPreload($featuredImageUrl, '', '');
        }

        $renderContext = array_merge(
            [
                'featured_image_url' => $featuredImageUrl,
                'collection_key' => (string) ($collection['collection_key'] ?? ''),
            ],
            $delivery->blockContext,
        );

        return $this->render('collection/show', [
            'title' => $translation['title'] ?? '',
            'excerpt' => $translation['excerpt'] ?? '',
            'published_at' => $entry['published_at'] ?? '',
            'featured_image' => $featuredImage,
            'collection' => $collection,
            'author_id' => $entry['author_id'] ?? null,
            'categories' => $entry['categories'] ?? [],
            'tags' => $entry['tags'] ?? [],
            'collectionName' => collection_display_title($collection),
            'collectionUrlPath' => $collectionUrlPath,
            'relatedEntries' => $relatedEntries,
            'showEntryHeading' => ! $hasHeroHeading,
            'showFeaturedImage' => ! $hasHeroImage,
            'lang' => $lang,
            'pageTitle' => $this->metadataValue($translation, 'meta_title', 'title'),
            'metaDescription' => $this->metadataValue($translation, 'meta_description', 'excerpt'),
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $ogImageUrl,
            'ogType' => in_array($translation['og_type'] ?? '', ['article', 'website'], true)
                ? $translation['og_type']
                : 'article',
            'articlePublishedTime' => $entry['published_at'] ?? null,
            'articleModifiedTime' => $this->dateValue($entry['updated_at'] ?? null),
            'metaRobots' => $this->metadataValue($translation, 'robots', null) ?: 'index, follow',
            'schemaData' => $this->schemaData($translation['schema_data'] ?? null),
            'renderedBlocks' => Services::blockRenderer()->render($blocks, $lang, $renderContext),
            'localized_urls' => $this->resolveEntryLocalizedUrls($collection, $entry, $lang, $resolvedSlug),
            '__layout_data' => $delivery->layout,
            'cacheScopes' => array_values(array_unique(array_merge(
                ['entries', 'pages', 'settings', 'menus'],
                is_array($renderContext['cacheScopes'] ?? null) ? $renderContext['cacheScopes'] : [],
            ))),
        ]);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function getEntryTranslation(array $entry, string $lang): array
    {
        $translations = is_array($entry['translations'] ?? null) ? $entry['translations'] : [];
        $translation = isset($entry['title']) ? $entry : [];

        foreach ($translations as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if (($candidate['language_id'] ?? null) === $lang || ($candidate['language_code'] ?? null) === $lang) {
                return array_merge($translation, $candidate);
            }
        }

        return $translation !== [] ? $translation : (is_array($translations[0] ?? null) ? $translations[0] : []);
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<array<string, mixed>>
     */
    private function entryBlocks(array $entry): array
    {
        $blocks = [];
        foreach (is_array($entry['blocks'] ?? null) ? $entry['blocks'] : [] as $block) {
            if (is_array($block)) {
                $normalized = [];
                foreach ($block as $key => $value) {
                    if (is_string($key)) {
                        $normalized[$key] = $value;
                    }
                }
                $blocks[] = $normalized;
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string> $keys
     */
    private function containsBlock(array $blocks, array $keys): bool
    {
        foreach ($blocks as $block) {
            if (in_array((string) ($block['block_key'] ?? ''), $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $translation */
    private function metadataValue(array $translation, string $preferred, ?string $fallback): string
    {
        $value = trim((string) ($translation[$preferred] ?? ''));
        if ($value !== '' || $fallback === null) {
            return $value;
        }

        return (string) ($translation[$fallback] ?? '');
    }

    private function dateValue(mixed $value): mixed
    {
        return is_array($value) ? ($value['date'] ?? null) : $value;
    }

    private function schemaData(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function currentCollectionPathFromRequest(): string
    {
        $path = trim((string) service('request')->getUri()->getPath(), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        if (in_array($segments[0] ?? '', config('App')->supportedLocales, true)) {
            array_shift($segments);
        }
        if (count($segments) > 1) {
            array_pop($segments);
        }

        $path = trim(implode('/', $segments), '/');

        return $path !== '' ? '/' . $path : '';
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
            if ($entrySlug !== '') {
                $localizedUrls[$locale] = site_url('/' . $locale . '/' . $collectionPath . '/' . $entrySlug);
            }
        }

        return $localizedUrls;
    }
}
