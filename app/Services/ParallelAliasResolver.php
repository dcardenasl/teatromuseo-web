<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AliasResolverInterface;
use App\Libraries\WebApiClientInterface;

class ParallelAliasResolver implements AliasResolverInterface
{
    /**
     * Mapping of resource types to their slug resolution endpoints.
     *
     * @var array<string, string>
     */
    private const ALIAS_ENDPOINTS = [
        'collection_items' => 'catalog/collection-items/by-slug',
        'events' => 'events/events/by-slug',
    ];

    public function __construct(
        private WebApiClientInterface $webApiClient
    ) {
    }

    public function resolveAlias(string $alias, string $type): ?string
    {
        $results = $this->resolveBatch([$alias], $type);
        return $results[$alias] ?? null;
    }

    public function resolveBatch(array $aliases, string $type, int $cacheTtl = 3600): array
    {
        if (empty($aliases)) {
            return [];
        }

        // Filter to unique, non-empty aliases
        $aliases = array_values(array_unique(array_filter(
            $aliases,
            static fn (mixed $alias): bool => is_string($alias) && $alias !== ''
        )));

        if (empty($aliases)) {
            return [];
        }

        if (!isset(self::ALIAS_ENDPOINTS[$type])) {
            return array_fill_keys($aliases, null);
        }

        $cache = \Config\Services::cache();
        $results = [];
        $miss = [];

        // First pass: check cache
        foreach ($aliases as $alias) {
            $cacheKey = $this->cacheKey($type, $alias);
            $cached = $cache->get($cacheKey);

            if ($cached !== null) {
                $results[$alias] = $cached;
            } else {
                $miss[] = $alias;
            }
        }

        // If all were cached, return early
        if (empty($miss)) {
            return $results;
        }

        // Fetch missing aliases from API
        $endpoint = self::ALIAS_ENDPOINTS[$type];
        $slugsParam = implode(',', array_map('urlencode', $miss));
        $path = "/api/v1/public/{$endpoint}?slugs={$slugsParam}";

        $response = $this->webApiClient->get($path, cacheTtl: $cacheTtl);

        if (!isset($response['data']) || !is_array($response['data'])) {
            // Mark missing aliases as null
            foreach ($miss as $alias) {
                $results[$alias] = null;
            }
            return $results;
        }

        $data = $response['data'];

        // Handle both array of items and paginated responses
        $items = $data;
        if (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
        }

        // Map slug responses to IDs
        $foundIds = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemSlug = (string) ($item['slug'] ?? '');
            $itemId = (string) ($item['id'] ?? '');

            if ($itemSlug !== '' && $itemId !== '') {
                $foundIds[$itemSlug] = $itemId;
            }
        }

        // Cache found IDs and populate results
        foreach ($miss as $alias) {
            if (isset($foundIds[$alias])) {
                $results[$alias] = $foundIds[$alias];
                $cache->save($this->cacheKey($type, $alias), $foundIds[$alias], $cacheTtl);
            } else {
                $results[$alias] = null;
                $cache->save($this->cacheKey($type, $alias), null, $cacheTtl);
            }
        }

        return $results;
    }

    /**
     * Build a cache key for an alias lookup.
     */
    private function cacheKey(string $type, string $alias): string
    {
        return 'alias_resolver_v1_' . $type . '_' . md5($alias);
    }
}
