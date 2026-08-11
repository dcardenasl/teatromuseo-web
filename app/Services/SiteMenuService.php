<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PublicPaths;

class SiteMenuService extends BaseSiteService
{
    private const CACHE_TTL = 600; // 10 minutes

    /**
     * Get a menu by key with its hierarchical tree structure.
     *
     * @return array<string, mixed>
     */
    public function getMenu(string $menuKey): array
    {
        $locale = (string) service('request')->getLocale();
        $nav = $this->fetchData("public-read/{$locale}/navigation", [], self::CACHE_TTL, 'menus') ?? [];

        $location = match ($menuKey) {
            'main', 'header' => 'main',
            'footer'          => 'footer',
            'legal'           => 'legal',
            default           => $menuKey,
        };

        $menu = isset($nav[$location]) && is_array($nav[$location])
            ? $nav[$location]
            : ['items' => []];

        if (is_array($menu['items'] ?? null)) {
            $items = [];
            foreach ($menu['items'] as $rawItem) {
                if (is_array($rawItem)) {
                    $items[] = $rawItem;
                }
            }
            $menu['items'] = $this->normalizeItems($items, $locale);
        }

        return $menu;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items, string $locale): array
    {
        foreach ($items as &$item) {
            if (! is_array($item)) {
                continue;
            }

            $navigation = is_array($item['navigation'] ?? null) ? $item['navigation'] : [];
            $routePath = PublicPaths::routePath((string) ($navigation['route_key'] ?? ''), $locale);
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
                        $candidateUrl = $entrySlug === 'home' ? '/' : '/' . $entrySlug;
                    }
                }

                $normalizedPath = PublicPaths::normalizeLocalizedPath(
                    $candidateUrl,
                    $locale,
                );
                if ($normalizedPath !== null) {
                    $item['custom_url'] = $normalizedPath;
                } elseif ($candidateUrl !== '') {
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
                $item['children'] = $this->normalizeItems($children, $locale);
            }
        }
        unset($item);

        return $items;
    }
}
