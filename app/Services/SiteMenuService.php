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
        $menu = $this->fetchData("public/menus/{$menuKey}", [], self::CACHE_TTL, 'menus') ?? ['items' => []];
        $locale = (string) service('request')->getLocale();

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
            if ($routePath !== null) {
                $item['custom_url'] = '/' . $routePath;
            } else {
                $normalizedPath = PublicPaths::normalizeLocalizedPath(
                    (string) ($item['custom_url'] ?? ''),
                    $locale,
                );
                if ($normalizedPath !== null) {
                    $item['custom_url'] = $normalizedPath;
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
