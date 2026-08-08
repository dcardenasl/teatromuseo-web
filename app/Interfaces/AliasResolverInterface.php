<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Resolves aliases (slugs) to resource IDs in batch.
 *
 * Used in Fase 4 (Smart Prefetch) to resolve slug-based references in blocks
 * to their numeric IDs in parallel, avoiding N+1 queries during prefetch.
 */
interface AliasResolverInterface
{
    /**
     * Resolve a single alias (slug) to its ID.
     *
     * @param string $alias The slug to resolve (e.g., "payaso", "museo")
     * @param string $type Resource type (collection_items, events, etc.)
     * @return ?string The resolved ID (numeric string), or null if not found
     */
    public function resolveAlias(string $alias, string $type): ?string;

    /**
     * Resolve multiple aliases in batch.
     *
     * Uses caching and parallel requests to resolve all aliases efficiently.
     *
     * Example return:
     *   [
     *     'payaso' => '42',
     *     'museo' => '15',
     *     'unknown' => null,
     *   ]
     *
     * @param array<string> $aliases Slugs to resolve
     * @param string $type Resource type (collection_items, events, etc.)
     * @param int $cacheTtl Seconds to cache results (default 3600)
     * @return array<string, string|null> Map of alias => ID (or null if not found)
     */
    public function resolveBatch(array $aliases, string $type, int $cacheTtl = 3600): array;
}
