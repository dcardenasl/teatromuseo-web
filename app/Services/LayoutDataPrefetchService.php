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
    public function prefetchLayoutData(array $data): array
    {
        $requests = [];
        $keys = [];

        if (! isset($data['mainMenu'])) {
            $keys[] = 'mainMenu';
            $requests[] = ['path' => 'public/menus/main', 'cacheTtl' => 600, 'scope' => 'menus'];
        }

        if (! isset($data['footerMenu'])) {
            $keys[] = 'footerMenu';
            $requests[] = ['path' => 'public/menus/footer', 'cacheTtl' => 600, 'scope' => 'menus'];
        }

        if (! isset($data['legalMenu'])) {
            $keys[] = 'legalMenu';
            $requests[] = ['path' => 'public/menus/legal', 'cacheTtl' => 600, 'scope' => 'menus'];
        }

        if (! isset($data['settings'])) {
            $keys[] = 'settings';
            $requests[] = ['path' => 'public/settings', 'cacheTtl' => 3600, 'scope' => 'settings'];
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
                // Fallback to empty data on failure
                $output[$key] = $key === 'settings' ? [] : ['items' => []];
                continue;
            }

            if ($key === 'settings') {
                $output[$key] = $result['data'];
            } else {
                // Menus need normalization (normalize items and route keys)
                $output[$key] = $this->normalizeMenuPayload($result['data']);
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
    private function normalizeMenuPayload(array $menu): array
    {
        $locale = (string) service('request')->getLocale();

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
            if ($routePath !== null) {
                $item['custom_url'] = '/' . $routePath;
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
