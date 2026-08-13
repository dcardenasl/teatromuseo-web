<?php

declare(strict_types=1);

namespace App\Services;

class LayoutDataPrefetchService extends BaseSiteService
{
    /**
     * Prefetch all layout data (menus, settings) in parallel using multiGet.
     * Returns only keys that are not already set in $data to avoid overwriting.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function prefetchLayoutData(array $data, ?string $locale = null): array
    {
        $requests = [];
        $keys = [];
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

        if ($missingMenuKeys !== []) {
            $keys[] = 'navigation';
            $requests[] = ['path' => "public-read/{$locale}/navigation", 'cacheTtl' => 600, 'scope' => 'menus'];

            // Menu items whose navigation payload omits `collection_slug`
            // fall back to resolveCollectionSlug(), which otherwise issues
            // its own uncached GET *after* this batch returns — a third,
            // strictly sequential round trip on every page (menus render
            // everywhere). Warm the same cache entry here so that fallback
            // becomes a cache hit instead of an extra network call.
            $keys[] = 'collections';
            $requests[] = ['path' => "public/{$locale}/collections", 'cacheTtl' => 3600, 'scope' => 'collections'];
        }

        if (! isset($data['settings'])) {
            $keys[] = 'settings';
            $requests[] = ['path' => "public-read/{$locale}/settings", 'cacheTtl' => 3600, 'scope' => 'settings'];
        }

        if ($requests === []) {
            return [];
        }

        $results = $this->apiClient->multiGet($requests);
        $output = [];

        foreach ($results as $i => $result) {
            $key = $keys[$i] ?? null;
            if ($key === null) {
                continue;
            }

            if ($result['ok'] === false || ! is_array($result['data'])) {
                if ($key === 'settings') {
                    $output['settings'] = [];
                } elseif ($key === 'navigation') {
                    foreach ($missingMenuKeys as $missingMenuKey) {
                        $output[$missingMenuKey] = ['items' => []];
                    }
                }
                continue;
            }

            if ($key === 'settings') {
                $output[$key] = $result['data'];
            } elseif ($key === 'navigation') {
                $navData = $result['data'];
                foreach ($menuKeys as $location => $menuKey) {
                    if (! in_array($menuKey, $missingMenuKeys, true)) {
                        continue;
                    }

                    $output[$menuKey] = isset($navData[$location]) && is_array($navData[$location])
                        ? $this->normalizeMenuPayload($navData[$location], $locale)
                        : ['items' => []];
                }
            }
        }

        if (! isset($data['socialLinks'])) {
            $settings = is_array($output['settings'] ?? null)
                ? $output['settings']
                : (is_array($data['settings'] ?? null) ? $data['settings'] : []);
            $output['socialLinks'] = \Config\Services::socialLinksService()->getActiveLinksFromSettings($settings);
        }

        return $output;
    }

    /**
     * Normalize a menu payload by processing items and route key resolution.
     *
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private function normalizeMenuPayload(array $menu, string $locale): array
    {
        if (is_array($menu['items'] ?? null)) {
            $items = [];
            foreach ($menu['items'] as $rawItem) {
                if (is_array($rawItem)) {
                    $items[] = $rawItem;
                }
            }
            $menu['items'] = $this->normalizeMenuItems($items, $locale);
        } else {
            $menu['items'] = [];
        }

        return $menu;
    }

    /**
     * Recursively normalize menu items and resolve route keys.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function normalizeMenuItems(array $items, string $locale): array
    {
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $navigation = is_array($item['navigation'] ?? null) ? $item['navigation'] : [];
            $routePath = \App\Support\PublicPaths::routePath((string) ($navigation['route_key'] ?? ''), $locale);
            $targetType = (string) ($navigation['target_type'] ?? '');
            $collectionSlug = $this->resolveCollectionSlug($locale, $navigation);
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
                $item['children'] = $this->normalizeMenuItems($children, $locale);
            }
        }
        unset($item);

        return $items;
    }
}
