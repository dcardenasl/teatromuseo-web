<?php

declare(strict_types=1);

namespace App\Services;

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
        return $this->fetchData("public/menus/{$menuKey}", [], self::CACHE_TTL, 'menus') ?? ['items' => []];
    }
}
