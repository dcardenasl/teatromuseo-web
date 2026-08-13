<?php

declare(strict_types=1);

namespace App\Services;

class LayoutDataPrefetchService extends BaseSiteService
{
    /**
     * Prefetch all layout data (menus, settings) in one request using the
     * composite `layout` PublicRead endpoint (ADR 006 in the CMS domain) —
     * navigation, collections and settings used to be three separate calls;
     * they're now one. Returns only keys that are not already set in $data
     * to avoid overwriting.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prefetchLayoutData(array $data, ?string $locale = null): array
    {
        $locale ??= (string) service('request')->getLocale();
        $menuKeys = [
            'main'   => 'mainMenu',
            'footer' => 'footerMenu',
            'legal'  => 'legalMenu',
        ];
        $missingMenuKeys = array_values(array_filter(
            $menuKeys,
            static fn (string $key): bool => ! isset($data[$key]),
        ));
        $needsSettings = ! isset($data['settings']);

        if ($missingMenuKeys === [] && ! $needsSettings) {
            return [];
        }

        $bootstrap = $this->fetchData("public-read/{$locale}/layout", [], 600, 'layout') ?? [];
        $navigation = is_array($bootstrap['navigation'] ?? null) ? $bootstrap['navigation'] : [];
        $rawCollections = is_array($bootstrap['collections'] ?? null) ? $bootstrap['collections'] : [];
        $collectionSlugsById = $this->collectionSlugsById(
            array_values(array_filter($rawCollections, static fn (mixed $collection): bool => is_array($collection))),
            $locale,
        );
        $settings = is_array($bootstrap['settings'] ?? null) ? $bootstrap['settings'] : [];

        $output = [];
        if ($needsSettings) {
            $output['settings'] = $settings;
        }

        $menuLocations = ['main' => 'mainMenu', 'footer' => 'footerMenu', 'legal' => 'legalMenu'];
        foreach ($missingMenuKeys as $menuKey) {
            $location = array_search($menuKey, $menuLocations, true);
            $output[$menuKey] = is_string($location) && isset($navigation[$location]) && is_array($navigation[$location])
                ? $this->normalizeMenuPayload($navigation[$location], $locale, $collectionSlugsById)
                : ['items' => []];
        }

        if (! isset($data['socialLinks'])) {
            $settingsForSocial = $needsSettings ? $settings : (is_array($data['settings'] ?? null) ? $data['settings'] : []);
            $output['socialLinks'] = \Config\Services::socialLinksService()->getActiveLinksFromSettings($settingsForSocial);
        }

        return $output;
    }

    /**
     * Build an id => slug map from the bootstrap's `collections` list, so menu
     * items that reference a collection by id resolve locally instead of the
     * BaseSiteService::resolveCollectionSlug() network fallback — the
     * collections list is already in hand from the same response.
     *
     * @param list<array<string, mixed>> $collections
     * @return array<int, string>
     */
    private function collectionSlugsById(array $collections, string $locale): array
    {
        $map = [];
        foreach ($collections as $collection) {
            if (! is_array($collection)) {
                continue;
            }
            $id = (int) ($collection['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $localizedSlugs = is_array($collection['localized_slugs'] ?? null) ? $collection['localized_slugs'] : [];
            $slug = trim((string) ($localizedSlugs[$locale] ?? $collection['slug'] ?? ''), '/');
            if ($slug !== '') {
                $map[$id] = $slug;
            }
        }

        return $map;
    }

    /**
     * Normalize a menu payload by processing items and route key resolution.
     *
     * @param array<string, mixed> $menu
     * @param array<int, string> $collectionSlugsById
     * @return array<string, mixed>
     */
    private function normalizeMenuPayload(array $menu, string $locale, array $collectionSlugsById): array
    {
        if (is_array($menu['items'] ?? null)) {
            $items = [];
            foreach ($menu['items'] as $rawItem) {
                if (is_array($rawItem)) {
                    $items[] = $rawItem;
                }
            }
            $menu['items'] = $this->normalizeMenuItems($items, $locale, $collectionSlugsById);
        } else {
            $menu['items'] = [];
        }

        return $menu;
    }

    /**
     * Recursively normalize menu items and resolve route keys.
     *
     * @param list<array<string, mixed>> $items
     * @param array<int, string> $collectionSlugsById
     * @return list<array<string, mixed>>
     */
    private function normalizeMenuItems(array $items, string $locale, array $collectionSlugsById): array
    {
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $navigation = is_array($item['navigation'] ?? null) ? $item['navigation'] : [];
            $routePath = \App\Support\PublicPaths::routePath((string) ($navigation['route_key'] ?? ''), $locale);
            $targetType = (string) ($navigation['target_type'] ?? '');
            $collectionSlug = $this->collectionSlugFromNavigation($navigation, $collectionSlugsById);
            $entrySlug = trim((string) ($navigation['slug'] ?? ''), '/');
            if (in_array($targetType, ['collection_listing', 'entry'], true) && $collectionSlug !== '') {
                $item['custom_url'] = '/' . $collectionSlug . ($targetType === 'entry' && $entrySlug !== '' ? '/' . $entrySlug : '');
            } elseif ($routePath !== null) {
                $item['custom_url'] = '/' . $routePath;
            } else {
                $candidateUrl = (string) ($item['custom_url'] ?? $item['url'] ?? '');
                if (($navigation['route_key'] ?? null) === 'pages') {
                    if ($entrySlug !== '') {
                        $candidateUrl = \App\Support\PublicPaths::isHomepageSlug($entrySlug, $locale)
                            ? \App\Support\PublicPaths::homepagePath($locale)
                            : '/' . $entrySlug;
                    }
                }

                $normalizedPath = \App\Support\PublicPaths::normalizeLocalizedPath(
                    $candidateUrl,
                    $locale,
                );
                if ($normalizedPath !== null) {
                    $item['custom_url'] = $normalizedPath;
                } elseif ($candidateUrl !== '') {
                    // Preserve external editorial URLs and internal page slugs
                    // that are not one of the known semantic aliases.
                    $item['custom_url'] = $candidateUrl;
                }
            }

            if (is_array($item['children'] ?? null)) {
                $children = [];
                foreach ($item['children'] as $rawChild) {
                    if (is_array($rawChild)) {
                        $children[] = $rawChild;
                    }
                }
                $item['children'] = $this->normalizeMenuItems($children, $locale, $collectionSlugsById);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Same precedence as BaseSiteService::resolveCollectionSlug(): an
     * explicit `collection_slug` on the navigation payload wins; otherwise
     * a collection/collection_index/collection_listing target resolves via
     * its id against the bootstrap's collections map.
     *
     * @param array<string, mixed> $navigation
     * @param array<int, string> $collectionSlugsById
     */
    private function collectionSlugFromNavigation(array $navigation, array $collectionSlugsById): string
    {
        $collectionSlug = trim((string) ($navigation['collection_slug'] ?? ''), '/');
        if ($collectionSlug !== '') {
            return $collectionSlug;
        }

        $targetType = (string) ($navigation['target_type'] ?? '');
        if (! in_array($targetType, ['collection', 'collection_index', 'collection_listing'], true)) {
            return '';
        }

        $collectionId = (int) ($navigation['target_id'] ?? 0);

        return $collectionId > 0 ? ($collectionSlugsById[$collectionId] ?? '') : '';
    }
}
